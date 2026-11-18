// =============================================================
// FERIAS SACABA — Comportamiento del panel (sin librerías externas)
// =============================================================
document.addEventListener('DOMContentLoaded', function () {

    // 1. Pedir confirmación antes de eliminar
    document.querySelectorAll('form[data-confirmar]').forEach(function (form) {
        form.addEventListener('submit', function (evento) {
            if (!window.confirm(form.dataset.confirmar)) {
                evento.preventDefault();
            }
        });
    });

    // 2. Filtros de las listas: buscar al cambiar la opción
    document.querySelectorAll('select[data-autoenviar]').forEach(function (select) {
        select.addEventListener('change', function () {
            select.form.submit();
        });
    });

    // 3. Campos que se muestran solo si otro campo tiene cierto valor
    //    (ej.: datos de la cuenta nueva del titular de un expositor)
    var condicionales = document.querySelectorAll('[data-si-campo]');
    function actualizarCondicionales() {
        condicionales.forEach(function (bloque) {
            var control = document.getElementById('campo_' + bloque.dataset.siCampo);
            var visible = control !== null && control.value === bloque.dataset.siValor;
            bloque.hidden = !visible;
            bloque.querySelectorAll('input, select, textarea').forEach(function (entrada) {
                entrada.disabled = !visible;
                entrada.required = visible && bloque.dataset.requerido === '1';
            });
        });
    }
    condicionales.forEach(function (bloque) {
        var control = document.getElementById('campo_' + bloque.dataset.siCampo);
        if (control) {
            control.addEventListener('change', actualizarCondicionales);
        }
    });
    actualizarCondicionales();

    // 4. Mostrar solo las áreas de la misma feria y categoría que la solicitud elegida
    document.querySelectorAll('select[data-filtrar-con]').forEach(function (select) {
        var origen = document.getElementById('campo_' + select.dataset.filtrarCon);
        var aviso = document.getElementById('aviso_' + select.name);
        if (!origen) {
            return;
        }
        function filtrar() {
            var elegida = origen.options[origen.selectedIndex];
            var feria = elegida ? elegida.dataset.feria : undefined;
            var categoria = elegida ? elegida.dataset.categoria : undefined;
            var visibles = 0;
            Array.prototype.forEach.call(select.options, function (opcion) {
                if (opcion.value === '') {
                    return;
                }
                var coincide = !feria || (opcion.dataset.feria === feria && opcion.dataset.categoria === categoria);
                opcion.hidden = !coincide;
                opcion.disabled = !coincide;
                if (coincide) {
                    visibles++;
                }
            });
            var actual = select.options[select.selectedIndex];
            if (actual && actual.disabled) {
                select.value = '';
            }
            if (aviso) {
                aviso.hidden = !(feria && visibles === 0);
                aviso.textContent = 'No hay áreas de esa feria y categoría. Regístrela primero en «Áreas».';
            }
        }
        origen.addEventListener('change', filtrar);
        filtrar();
    });

    // 5. Limitar los rubros de la solicitud a la feria elegida.
    var feriaSolicitud = document.getElementById('feria_id');
    var categoriaSolicitud = document.getElementById('categoria_id');
    if (feriaSolicitud && categoriaSolicitud) {
        function filtrarCategorias() {
            var feria = feriaSolicitud.value;
            Array.prototype.forEach.call(categoriaSolicitud.options, function (opcion) {
                if (opcion.value === '') {
                    return;
                }
                var coincide = opcion.dataset.feria === feria;
                opcion.hidden = !coincide;
                opcion.disabled = !coincide;
            });
            var elegida = categoriaSolicitud.options[categoriaSolicitud.selectedIndex];
            if (elegida && elegida.disabled) {
                categoriaSolicitud.value = '';
            }
        }
        feriaSolicitud.addEventListener('change', filtrarCategorias);
        filtrarCategorias();
    }
});
