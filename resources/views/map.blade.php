<!doctype html>
<html lang="pt-br">

<head>
    <meta charset="utf-8" />
    <title>Medição de Área - Mapbox GL Draw + Turf.js</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://api.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.css" rel="stylesheet" />
    <link href="https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-draw/v1.5.0/mapbox-gl-draw.css" rel="stylesheet" />
    <style>
        html,
        body {
            height: 100%;
            margin: 0;
        }

        #map {
            position: absolute;
            top: 0;
            bottom: 0;
            left: 0;
            right: 320px;
        }

        #panel {
            position: absolute;
            top: 0;
            right: 0;
            width: 320px;
            height: 100%;
            padding: 14px;
            box-sizing: border-box;
            background: #111;
            color: #f0f0f0;
            font: 14px/1.4 system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            border-left: 1px solid #222;
        }

        h2 {
            margin: 0 0 10px;
            font-size: 16px;
        }

        .metric {
            margin-bottom: 12px;
            background: #181818;
            border: 1px solid #2a2a2a;
            border-radius: 10px;
            padding: 10px;
        }

        .metric b {
            display: block;
            font-size: 12px;
            color: #bbb;
            margin-bottom: 6px;
        }

        .metric .val {
            font-size: 18px;
        }

        button {
            background: #202020;
            color: #eee;
            border: 1px solid #2a2a2a;
            border-radius: 8px;
            padding: 8px 10px;
            cursor: pointer;
            margin-right: 8px;
        }

        button:hover {
            background: #2a2a2a;
        }

        .footer {
            position: absolute;
            bottom: 10px;
            right: 10px;
            color: #999;
            font-size: 12px;
        }
    </style>
</head>

<body>
    <div id="map"></div>

    <aside id="panel">
        <h2>Medição de Área</h2>
        <div class="metric">
            <b>Área (hectares)</b>
            <div class="val" id="area-ha">0 ha</div>
        </div>
        <div class="metric">
            <b>Área (m²)</b>
            <div class="val" id="area-m2">0 m²</div>
        </div>
        <div class="metric">
            <b>Perímetro</b>
            <div class="val" id="peri-km">0 km</div>
        </div>

        <button id="btn-clear-all">Limpar Tudo</button>
        <div class="footer">Mapbox GL Draw + Turf.js</div>
    </aside>

    <script src="https://api.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@turf/turf@6.5.0/turf.min.js"></script>
    <script src="https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-draw/v1.5.0/mapbox-gl-draw.js"></script>

    <script>
        mapboxgl.accessToken = 'pk.eyJ1IjoicGVkcm9sYXNrYXdza2kxIiwiYSI6ImNtaGd6bGhiYTBtbWQyaHE2eDZwOTB6d2wifQ.n_mdP_6WzXq2b8auu6LVog';

        const map = new mapboxgl.Map({
            container: 'map',
            style: 'mapbox://styles/mapbox/satellite-streets-v12',
            center: [-51.9253, -14.2350],
            zoom: 4.1
        });

        map.addControl(new mapboxgl.NavigationControl(), 'top-left');
        map.addControl(new mapboxgl.ScaleControl({
            unit: 'metric'
        }));

        // Draw somente com polígono
        const draw = new MapboxDraw({
            displayControlsDefault: false,
            controls: {
                polygon: true,
                trash: true
            },
            defaultMode: 'draw_polygon',
            styles: [{
                    id: 'gl-draw-polygon-fill',
                    type: 'fill',
                    filter: ['all', ['==', '$type', 'Polygon'],
                        ['!=', 'mode', 'static']
                    ],
                    paint: {
                        'fill-color': '#3bb2d0',
                        'fill-opacity': 0.25
                    }
                },
                {
                    id: 'gl-draw-polygon-stroke-active',
                    type: 'line',
                    filter: ['all', ['==', '$type', 'Polygon'],
                        ['!=', 'mode', 'static']
                    ],
                    paint: {
                        'line-color': '#3bb2d0',
                        'line-width': 2
                    }
                },
                {
                    id: 'gl-draw-polygon-and-line-vertex-active',
                    type: 'circle',
                    filter: ['all', ['==', 'meta', 'vertex'],
                        ['==', '$type', 'Point'],
                        ['!=', 'mode', 'static']
                    ],
                    paint: {
                        'circle-radius': 4,
                        'circle-color': '#fff',
                        'circle-stroke-color': '#3bb2d0',
                        'circle-stroke-width': 2
                    }
                }
            ]
        });
        map.addControl(draw, 'top-left');

        document.getElementById('btn-clear-all').onclick = () => draw.deleteAll();

        function updateMetrics() {
            const data = draw.getAll();
            let totalAreaM2 = 0;
            let totalPeriKm = 0;

            for (const f of data.features) {
                if (f.geometry.type === 'Polygon' || f.geometry.type === 'MultiPolygon') {
                    const areaM2 = turf.area(f);
                    totalAreaM2 += areaM2;
                    const outline = turf.polygonToLine(f);
                    totalPeriKm += turf.length(outline, {
                        units: 'kilometers'
                    });
                }
            }

            const ha = totalAreaM2 / 10000;
            document.getElementById('area-ha').textContent = ha.toLocaleString('pt-BR', {
                maximumFractionDigits: 2
            }) + ' ha';
            document.getElementById('area-m2').textContent = totalAreaM2.toLocaleString('pt-BR', {
                maximumFractionDigits: 0
            }) + ' m²';
            document.getElementById('peri-km').textContent = totalPeriKm.toLocaleString('pt-BR', {
                maximumFractionDigits: 2
            }) + ' km';
        }

        map.on('load', updateMetrics);
        map.on('draw.create', updateMetrics);
        map.on('draw.update', updateMetrics);
        map.on('draw.delete', updateMetrics);
    </script>
</body>

</html>
