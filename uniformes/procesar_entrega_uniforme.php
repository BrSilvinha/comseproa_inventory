<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ejemplo de Entrega de Uniformes</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .ejemplo-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .titulo {
            color: #0a253c;
            border-bottom: 3px solid #ff6b35;
            padding-bottom: 10px;
            margin-bottom: 25px;
        }
        .seccion {
            margin-bottom: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #28a745;
        }
        .seccion h3 {
            margin-top: 0;
            color: #0a253c;
        }
        .datos-trabajador {
            background: #e3f2fd;
            border-left-color: #2196f3;
        }
        .uniformes-lista {
            background: #fff3e0;
            border-left-color: #ff9800;
        }
        .resultado {
            background: #e8f5e8;
            border-left-color: #4caf50;
        }
        .codigo {
            background: #f1f3f4;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            overflow-x: auto;
            margin: 10px 0;
        }
        .producto-item {
            background: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .producto-nombre {
            font-weight: bold;
            color: #0a253c;
        }
        .producto-detalles {
            color: #666;
            font-size: 14px;
        }
        .stock-info {
            color: #28a745;
            font-weight: bold;
        }
        .alerta {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .paso {
            counter-increment: paso;
            position: relative;
            padding-left: 40px;
        }
        .paso::before {
            content: counter(paso);
            position: absolute;
            left: 0;
            top: 0;
            background: #0a253c;
            color: white;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }
        .container-pasos {
            counter-reset: paso;
        }
    </style>
</head>
<body>
    <div class="ejemplo-container">
        <h1 class="titulo">📋 Ejemplo Práctico: Entrega de Uniformes</h1>
        
        <div class="alerta">
            <strong>🎯 Caso de Uso:</strong> Entrega de uniformes completos a un nuevo trabajador de seguridad
        </div>

        <div class="container-pasos">
            <div class="seccion datos-trabajador paso">
                <h3>👤 Datos del Trabajador</h3>
                <div class="codigo">
<strong>Nombre:</strong> Carlos Alberto Mendoza López
<strong>DNI:</strong> 12345678
<strong>Puesto:</strong> Agente de Seguridad
<strong>Almacén:</strong> Grupo Seal - Motupe
                </div>
            </div>

            <div class="seccion uniformes-lista paso">
                <h3>👕 Uniformes a Entregar</h3>
                
                <div class="producto-item">
                    <div class="producto-nombre">Camisa Blanca</div>
                    <div class="producto-detalles">
                        Color: Blanca | Talla: XL | Modelo: Comseproa SAC<br>
                        <span class="stock-info">Stock disponible: 11 unidades</span>
                    </div>
                    <strong>Cantidad a entregar: 2 unidades</strong>
                </div>

                <div class="producto-item">
                    <div class="producto-nombre">Pantalón</div>
                    <div class="producto-detalles">
                        Color: Azul | Talla: 38<br>
                        <span class="stock-info">Stock disponible: 2 unidades</span>
                    </div>
                    <strong>Cantidad a entregar: 1 unidad</strong>
                </div>

                <div class="producto-item">
                    <div class="producto-nombre">Botas de seguridad</div>
                    <div class="producto-detalles">
                        Color: Negro | Talla: 43<br>
                        <span class="stock-info">Stock disponible: 12 unidades</span>
                    </div>
                    <strong>Cantidad a entregar: 1 par</strong>
                </div>

                <div class="producto-item">
                    <div class="producto-nombre">Casco</div>
                    <div class="producto-detalles">
                        Color: Blanco | Modelo: Bellsafe<br>
                        <span class="stock-info">Stock disponible: 8 unidades</span>
                    </div>
                    <strong>Cantidad a entregar: 1 unidad</strong>
                </div>

                <div class="producto-item">
                    <div class="producto-nombre">Chaleco</div>
                    <div class="producto-detalles">
                        Color: Azul | Talla: XL | Modelo: Grupo Seal<br>
                        <span class="stock-info">Stock disponible: 4 unidades</span>
                    </div>
                    <strong>Cantidad a entregar: 1 unidad</strong>
                </div>
            </div>

            <div class="seccion paso">
                <h3>🔄 Proceso en el Sistema</h3>
                <ol>
                    <li><strong>Ingresar datos del trabajador:</strong> Nombre completo y DNI de 8 dígitos</li>
                    <li><strong>Seleccionar uniformes:</strong> Elegir cada producto de la lista disponible</li>
                    <li><strong>Definir cantidades:</strong> Especificar cuántas unidades de cada uniforme</li>
                    <li><strong>Confirmar entrega:</strong> El sistema valida stock y procesa automáticamente</li>
                </ol>
            </div>

            <div class="seccion resultado paso">
                <h3>✅ Resultado Esperado</h3>
                
                <h4>📊 Registros Creados:</h4>
                <div class="codigo">
<strong>En tabla 'entrega_uniformes':</strong>
- 5 registros (uno por cada producto entregado)
- Fecha y hora de entrega: 2025-06-02 14:30:00
- Usuario responsable: Jhamir Alexander Silva Baldera

<strong>Actualización de stock:</strong>
- Camisa Blanca XL: 11 → 9 unidades
- Pantalón Azul 38: 2 → 1 unidad  
- Botas seguridad 43: 12 → 11 unidades
- Casco Blanco: 8 → 7 unidades
- Chaleco Azul XL: 4 → 3 unidades

<strong>En tabla 'movimientos':</strong>
- 5 movimientos tipo "salida" registrados
- Descripción: "Entrega de uniforme a Carlos Alberto Mendoza López (DNI: 12345678)"

<strong>En tabla 'logs_actividad':</strong>
- Log: "Entregó 6 uniformes a Carlos Alberto Mendoza López (DNI: 12345678)"
                </div>

                <h4>🔍 Verificación en Historial:</h4>
                <div class="codigo">
Fecha: 02/06/2025 14:30
Trabajador: Carlos Alberto Mendoza López  
DNI: 12345678
Productos: 5 tipos de uniformes (6 unidades total)
Almacén: Grupo Seal - Motupe
Responsable: Jhamir Alexander Silva Baldera
                </div>
            </div>

            <div class="seccion paso">
                <h3>🚀 Pasos para Implementar</h3>
                <ol>
                    <li><strong>Usar los archivos corregidos</strong> que proporcioné anteriormente</li>
                    <li><strong>La tabla ya existe</strong> en tu BD (entrega_uniformes)</li>
                    <li><strong>El ID de categoría ya está corregido</strong> (categoría "Ropa" = ID 1)</li>
                    <li><strong>Probar con datos reales</strong> usando los productos disponibles</li>
                </ol>
            </div>
        </div>

        <div class="alerta">
            <strong>💡 Consejo:</strong> Empieza probando con cantidades pequeñas para verificar que todo funciona correctamente antes de hacer entregas masivas.
        </div>
    </div>
</body>
</html>