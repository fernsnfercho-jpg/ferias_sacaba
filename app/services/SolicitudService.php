<?php
declare(strict_types=1);

final class SolicitudService
{
    /**
     * Reserva una celda de la cuadrícula 3×4. El bloqueo de la feria serializa
     * las reservas de esa feria antes de volver a comprobar el plano.
     */
    public function reservarCarpa(int $feriaId, int $expositorId, int $categoriaId, string $codigoElegido): array
    {
        $db = Database::conectar();
        $db->beginTransaction();
        try {
            $bloqueo = $db->prepare(
                "SELECT id FROM ferias
                 WHERE id = :id AND estado = 'abierta' AND fecha >= CURRENT_DATE
                 FOR UPDATE"
            );
            $bloqueo->execute(['id' => $feriaId]);
            if ($bloqueo->fetchColumn() === false) {
                throw new DomainException('La feria seleccionada ya no recibe reservas.');
            }

            if ((new Solicitud())->existeEnFeria($feriaId, $expositorId, null)) {
                throw new DomainException('Ya tiene una reserva registrada para esta feria.');
            }

            $carpa = (new TipoCarpa())->oficial();
            if ((float) $carpa['ancho_m'] !== 3.0 || (float) $carpa['largo_m'] !== 4.0) {
                throw new DomainException('La carpa oficial debe medir 3 × 4 metros. Revise la configuración.');
            }

            $celdaElegida = null;
            $areaElegida = null;
            $plano = (new UbicacionService())->planoDeFeria($feriaId, $categoriaId);
            foreach ($plano['zonas'] as $zona) {
                foreach ($zona['celdas'] as $celda) {
                    if (strtoupper((string) $celda['codigo']) === strtoupper($codigoElegido)
                        && $celda['estado'] === 'libre') {
                        $celdaElegida = $celda;
                        $areaElegida = $zona['area'];
                        break 2;
                    }
                }
            }
            if ($celdaElegida === null || $areaElegida === null) {
                throw new DomainException('Esa carpa ya no está disponible. Actualice el plano y elija otra.');
            }

            $solicitudes = new Solicitud();
            $solicitudId = $solicitudes->crear([
                'feria_id' => $feriaId,
                'expositor_id' => $expositorId,
                'categoria_id' => $categoriaId,
                'tipo_carpa_id' => (int) $carpa['id'],
                'estado' => 'pendiente',
            ]);

            (new Asignacion())->crear([
                'feria_id' => $feriaId,
                'solicitud_id' => $solicitudId,
                'area_id' => (int) $areaElegida['id'],
                'codigo' => (string) $celdaElegida['codigo'],
                'x_m' => $celdaElegida['x_m'],
                'y_m' => $celdaElegida['y_m'],
                'ancho_m' => $carpa['ancho_m'],
                'largo_m' => $carpa['largo_m'],
                'fijada' => 0,
            ]);

            $db->commit();
            return [
                'solicitud_id' => $solicitudId,
                'codigo' => (string) $celdaElegida['codigo'],
                'estado' => 'pendiente',
            ];
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($e->getCode() === '23000') {
                throw new DomainException('La carpa o la reserva ya fue ocupada. Actualice el plano e inténtelo de nuevo.');
            }
            throw $e;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function confirmar(int $solicitudId): array
    {
        $db = Database::conectar();
        $db->beginTransaction();
        try {
            $solicitud = $this->bloquearSolicitud($solicitudId);
            if ($solicitud === null) {
                throw new DomainException('La reserva no existe.');
            }
            if (Solicitud::normalizar((string) $solicitud['estado']) !== 'pendiente') {
                throw new DomainException('Solo se pueden confirmar reservas pendientes.');
            }

            $db->prepare("UPDATE solicitudes SET estado = 'confirmada', confirmada_en = NOW() WHERE id = :id")
                ->execute(['id' => $solicitudId]);
            $db->commit();

            return ['solicitud_id' => $solicitudId, 'estado' => 'confirmada', 'datos' => (new Solicitud())->buscarDetalle($solicitudId)];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function rechazar(int $solicitudId, string $motivo): array
    {
        $motivo = trim($motivo);
        if (longitud($motivo) > 255) {
            throw new DomainException('El motivo no puede superar 255 caracteres.');
        }
        $db = Database::conectar();
        $db->beginTransaction();
        try {
            $solicitud = $this->bloquearSolicitud($solicitudId);
            if ($solicitud === null) {
                throw new DomainException('La reserva no existe.');
            }
            if (!in_array(Solicitud::normalizar((string) $solicitud['estado']), ['pendiente', 'confirmada'], true)) {
                throw new DomainException('Solo se pueden rechazar reservas pendientes o confirmadas.');
            }

            $codigo = (string) ($solicitud['codigo'] ?? '');
            if (!empty($solicitud['asignacion_id'])) {
                $db->prepare('DELETE FROM asignaciones WHERE id = :id')
                    ->execute(['id' => (int) $solicitud['asignacion_id']]);
            }
            $db->prepare(
                "UPDATE solicitudes
                 SET estado = 'rechazada', motivo_rechazo = :motivo, codigo_liberado = :codigo
                 WHERE id = :id"
            )->execute(['motivo' => $motivo !== '' ? $motivo : null, 'codigo' => $codigo !== '' ? $codigo : null, 'id' => $solicitudId]);
            $db->commit();

            return ['solicitud_id' => $solicitudId, 'estado' => 'rechazada', 'datos' => (new Solicitud())->buscarDetalle($solicitudId)];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function cancelar(int $solicitudId, int $expositorId): array
    {
        $db = Database::conectar();
        $db->beginTransaction();
        try {
            $solicitud = $this->bloquearSolicitud($solicitudId);
            if ($solicitud === null) {
                throw new DomainException('La reserva no existe.');
            }
            if ((int) $solicitud['expositor_id'] !== $expositorId) {
                throw new DomainException('No tiene permiso para cancelar esta reserva.');
            }
            if (Solicitud::normalizar((string) $solicitud['estado']) !== 'pendiente') {
                throw new DomainException('Solo se pueden cancelar reservas pendientes.');
            }

            $codigo = (string) ($solicitud['codigo'] ?? '');
            if (!empty($solicitud['asignacion_id'])) {
                $db->prepare('DELETE FROM asignaciones WHERE id = :id')
                    ->execute(['id' => (int) $solicitud['asignacion_id']]);
            }
            $db->prepare(
                "UPDATE solicitudes SET estado = 'cancelada', codigo_liberado = :codigo WHERE id = :id"
            )->execute(['codigo' => $codigo !== '' ? $codigo : null, 'id' => $solicitudId]);
            $db->commit();

            return ['solicitud_id' => $solicitudId, 'estado' => 'cancelada', 'datos' => (new Solicitud())->buscarDetalle($solicitudId)];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private function bloquearSolicitud(int $id): ?array
    {
        $db = Database::conectar();
        $lock = $db->prepare('SELECT id FROM solicitudes WHERE id = :id FOR UPDATE');
        $lock->execute(['id' => $id]);
        if ($lock->fetchColumn() === false) {
            return null;
        }
        return (new Solicitud())->buscarDetalle($id);
    }
}
