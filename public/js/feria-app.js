(function () {
    'use strict';

    var feria = document.getElementById('feria_id');
    var categoria = document.getElementById('categoria_id');
    var area = document.getElementById('area_id');

    function filtrarOpciones() {
        if (!feria || !categoria || !area) {
            return;
        }
        var feriaId = feria.value;
        Array.prototype.forEach.call(categoria.options, function (option) {
            if (!option.value) {
                return;
            }
            option.hidden = option.dataset.feria !== feriaId;
            option.disabled = option.hidden;
        });
        if (categoria.selectedOptions.length && categoria.selectedOptions[0].disabled) {
            categoria.value = '';
        }
        var categoriaId = categoria.value;
        Array.prototype.forEach.call(area.options, function (option) {
            if (!option.value) {
                return;
            }
            var matches = option.dataset.feria === feriaId && option.dataset.categoria === categoriaId;
            option.hidden = !matches;
            option.disabled = !matches;
        });
        if (area.selectedOptions.length && area.selectedOptions[0].disabled) {
            area.value = '';
        }
    }

    if (feria) {
        feria.addEventListener('change', filtrarOpciones);
    }
    if (categoria) {
        categoria.addEventListener('change', filtrarOpciones);
    }
    filtrarOpciones();

    var solicitudForm = document.querySelector('.portal-form');
    var mapaDatos = document.getElementById('reservation-map-data');
    if (solicitudForm && mapaDatos && feria && categoria && area) {
        var tipoCarpa = document.getElementById('tipo_carpa_id');
        var escenario = document.getElementById('reservation-map-stage');
        var foto = document.getElementById('reservation-map-photo');
        var placeholder = document.getElementById('reservation-map-placeholder');
        var puntos = document.getElementById('reservation-map-spots');
        var estadoPuesto = document.getElementById('map-selection-status');
        var dimensiones = document.getElementById('map-dimensions');
        var entradaX = document.getElementById('puesto_x_m');
        var entradaY = document.getElementById('puesto_y_m');
        var errorPuesto = document.getElementById('puesto-error');
        var datos = JSON.parse(mapaDatos.textContent);
        var limitePuestos = 500;

        function rectanguloLibre(x, y, ancho, largo, ocupados) {
            return !ocupados.some(function (puesto) {
                var px = Math.round(puesto.x_m * 100);
                var py = Math.round(puesto.y_m * 100);
                var pw = Math.round(puesto.ancho_m * 100);
                var ph = Math.round(puesto.largo_m * 100);
                return x < px + pw && px < x + ancho && y < py + ph && py < y + largo;
            });
        }

        function posicionPuesto(elemento, puesto, area, largoUtil) {
            elemento.style.left = (puesto.x_m / area.ancho_m * 100) + '%';
            elemento.style.top = (puesto.y_m / largoUtil * 100) + '%';
            elemento.style.width = (puesto.ancho_m / area.ancho_m * 100) + '%';
            elemento.style.height = (puesto.largo_m / largoUtil * 100) + '%';
        }

        function actualizarPlano() {
            errorPuesto.hidden = true;
            errorPuesto.textContent = '';
            puntos.replaceChildren();
            var xAnterior = entradaX.value;
            var yAnterior = entradaY.value;

            var opcionFeria = feria.selectedOptions[0];
            var feriaId = feria.value;
            var categoriaId = categoria.value;
            var areaId = area.value;
            var carpa = tipoCarpa && tipoCarpa.selectedOptions[0];
            var ancho = carpa ? Math.round(Number(carpa.dataset.ancho) * 100) : 0;
            var largo = carpa ? Math.round(Number(carpa.dataset.largo) * 100) : 0;
            var seleccionArea = datos.areas.find(function (item) {
                return String(item.feria_id) === feriaId &&
                    String(item.categoria_id) === categoriaId &&
                    String(item.id) === areaId;
            });

            if (!opcionFeria || !feriaId || !seleccionArea || !ancho || !largo) {
                escenario.hidden = true;
                estadoPuesto.textContent = 'Selecciona feria, rubro, área y carpa';
                dimensiones.textContent = 'Los espacios se ajustarán a las medidas de la carpa elegida.';
                return;
            }

            var imagen = opcionFeria.dataset.imagen || '';
            foto.hidden = !imagen;
            placeholder.hidden = Boolean(imagen);
            if (imagen) {
                foto.src = imagen;
                foto.alt = 'Foto del lugar de ' + opcionFeria.textContent.trim();
                foto.onerror = function () {
                    foto.hidden = true;
                    placeholder.hidden = false;
                };
            } else {
                foto.removeAttribute('src');
            }

            escenario.hidden = false;
            var anchoArea = Math.round(Number(seleccionArea.ancho_m) * 100);
            var largoUtil = Math.max(0, Math.round((Number(seleccionArea.largo_m) -
                Number(seleccionArea.pasillo_m)) * 100));
            escenario.style.aspectRatio = anchoArea + ' / ' + Math.max(1, largoUtil);
            var ocupados = seleccionArea.puestos || [];

            ocupados.forEach(function (puesto) {
                var marca = document.createElement('span');
                marca.className = 'reservation-map-occupied';
                marca.textContent = puesto.codigo + ' · ocupado';
                marca.setAttribute('aria-label', puesto.codigo + ', puesto ocupado');
                posicionPuesto(marca, puesto, seleccionArea, largoUtil / 100);
                puntos.appendChild(marca);
            });

            var columnas = Math.floor(anchoArea / ancho);
            var filas = Math.floor(largoUtil / largo);
            var totalCeldas = columnas * filas;
            var libres = 0;
            for (var fila = 0; fila < filas && libres < limitePuestos; fila++) {
                for (var columna = 0; columna < columnas && libres < limitePuestos; columna++) {
                    var x = columna * ancho;
                    var y = fila * largo;
                    if (!rectanguloLibre(x, y, ancho, largo, ocupados)) {
                        continue;
                    }
                    var boton = document.createElement('button');
                    boton.type = 'button';
                    boton.className = 'reservation-map-spot';
                    boton.textContent = 'F' + (fila + 1) + ' · P' + (columna + 1);
                    boton.setAttribute('aria-pressed', 'false');
                    boton.setAttribute('aria-label', 'Seleccionar fila ' + (fila + 1) +
                        ', puesto ' + (columna + 1) + ', lugar libre');
                    var ubicacion = {
                        x_m: x / 100,
                        y_m: y / 100,
                        ancho_m: ancho / 100,
                        largo_m: largo / 100
                    };
                    posicionPuesto(boton, ubicacion, seleccionArea, largoUtil / 100);
                    boton.addEventListener('click', function (evento) {
                        var elegido = evento.currentTarget;
                        puntos.querySelectorAll('.reservation-map-spot').forEach(function (otro) {
                            otro.setAttribute('aria-pressed', 'false');
                        });
                        elegido.setAttribute('aria-pressed', 'true');
                        entradaX.value = elegido.dataset.x;
                        entradaY.value = elegido.dataset.y;
                        estadoPuesto.textContent = elegido.getAttribute('aria-label').replace('Seleccionar ', '').replace(', lugar libre', '');
                        errorPuesto.hidden = true;
                    });
                    boton.dataset.x = ubicacion.x_m.toFixed(2);
                    boton.dataset.y = ubicacion.y_m.toFixed(2);
                    if (boton.dataset.x === xAnterior && boton.dataset.y === yAnterior) {
                        boton.setAttribute('aria-pressed', 'true');
                    }
                    puntos.appendChild(boton);
                    libres++;
                }
            }

            var puestoElegido = puntos.querySelector('.reservation-map-spot[aria-pressed="true"]');
            estadoPuesto.textContent = puestoElegido
                ? puestoElegido.getAttribute('aria-label').replace('Seleccionar ', '').replace(', lugar libre', '')
                : (libres ? libres + ' lugar(es) libre(s) · toca uno para elegir'
                    : 'No hay lugar para ese tamaño en esta área');
            dimensiones.textContent = seleccionArea.nombre + ' · ' +
                seleccionArea.ancho_m + ' × ' + seleccionArea.largo_m + ' m · carpa ' +
                (ancho / 100) + ' × ' + (largo / 100) + ' m';
            if (totalCeldas > limitePuestos) {
                dimensiones.textContent += ' · Se muestran las primeras ' + limitePuestos +
                    ' posiciones para facilitar la selección.';
            }
        }

        [feria, categoria, area, tipoCarpa].forEach(function (control) {
            if (control) {
                control.addEventListener('change', function () {
                    entradaX.value = '';
                    entradaY.value = '';
                    actualizarPlano();
                });
            }
        });
        solicitudForm.addEventListener('submit', function (evento) {
            if (entradaX.value === '' || entradaY.value === '') {
                evento.preventDefault();
                errorPuesto.textContent = 'Selecciona un puesto libre en el plano antes de reservar.';
                errorPuesto.hidden = false;
                escenario.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
        actualizarPlano();
    }

    var sortButtons = document.querySelectorAll('[data-order]');
    sortButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var order = button.dataset.order;
            sortButtons.forEach(function (item) {
                item.setAttribute('aria-pressed', item === button ? 'true' : 'false');
            });
            document.querySelectorAll('.map-stands').forEach(function (grid) {
                var cards = Array.prototype.slice.call(grid.children);
                cards.sort(function (a, b) {
                    if (order === 'area') {
                        return a.dataset.area.localeCompare(b.dataset.area, 'es') ||
                            a.querySelector('strong').textContent.localeCompare(b.querySelector('strong').textContent, 'es');
                    }
                    var key = order === 'institution' ? 'institution' :
                        (order === 'name' ? 'name' : 'category');
                    var first = a.dataset[key] || '\uffff';
                    var second = b.dataset[key] || '\uffff';
                    return first.localeCompare(second, 'es', { sensitivity: 'base' }) ||
                        a.dataset.area.localeCompare(b.dataset.area, 'es');
                });
                cards.forEach(function (card) {
                    grid.appendChild(card);
                });
            });
        });
    });

    var success = document.querySelector('[data-confetti="true"]');
    if (success && window.matchMedia('(prefers-reduced-motion: no-preference)').matches) {
        var colors = ['#1a3a8f', '#ff7a00', '#72d9a8', '#f4c95d'];
        for (var i = 0; i < 36; i++) {
            var particle = document.createElement('span');
            particle.className = 'confetti-piece';
            particle.style.left = Math.random() * 100 + 'vw';
            particle.style.backgroundColor = colors[i % colors.length];
            particle.style.animationDelay = Math.random() * .7 + 's';
            document.body.appendChild(particle);
            window.setTimeout(function (node) {
                node.remove();
            }, 2400, particle);
        }
    }
})();
