

 

<?php
require_once 'conexion.php';

// Configuración de errores para desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Obtener parámetros de filtrado con saneamiento
$filtros = [
    'semestre' => isset($_GET['semestre']) ? intval($_GET['semestre']) : null,
    'turno' => isset($_GET['turno']) ? intval($_GET['turno']) : null,
    'grupo' => isset($_GET['grupo']) ? intval($_GET['grupo']) : null,
    'promedio' => isset($_GET['promedio']) ? $_GET['promedio'] : null,
    'busqueda' => isset($_GET['busqueda']) ? trim($_GET['busqueda']) : null
];

// Consulta para semestres y turnos
$semestres = $conn->query("SELECT * FROM Semestres WHERE activo = TRUE ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$turnos = $conn->query("SELECT * FROM Turnos ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Consulta para grupos filtrados
$sqlGrupos = "SELECT g.grupo_id, g.nombre FROM Grupos g WHERE 1=1";
if ($filtros['semestre']) {
    $sqlGrupos .= " AND g.semestre_id = " . $filtros['semestre'];
}
if ($filtros['turno']) {
    $sqlGrupos .= " AND g.turno_id = " . $filtros['turno'];
}
$grupos = $conn->query($sqlGrupos)->fetchAll(PDO::FETCH_ASSOC);

// Consulta principal para alumnos
$sql = "SELECT 
            a.alumno_id, 
            a.numero_de_boleta, 
            a.nombre, 
            a.apellido_paterno, 
            a.apellido_materno,
            g.nombre AS grupo_nombre,
            s.nombre AS semestre_nombre,
            t.nombre AS turno_nombre,
            ROUND(AVG(c.final), 2) AS promedio_general
        FROM Alumnos a
        JOIN Grupos g ON a.grupo_id = g.grupo_id
        JOIN Semestres s ON g.semestre_id = s.semestre_id
        JOIN Turnos t ON g.turno_id = t.turno_id
        LEFT JOIN Calificaciones c ON a.alumno_id = c.alumno_id
        WHERE a.activo = TRUE";

// Aplicar filtros
if ($filtros['semestre']) {
    $sql .= " AND g.semestre_id = :semestre";
}
if ($filtros['turno']) {
    $sql .= " AND g.turno_id = :turno";
}
if ($filtros['grupo']) {
    $sql .= " AND g.grupo_id = :grupo";
}
if ($filtros['busqueda']) {
    $sql .= " AND (a.nombre LIKE :busqueda OR 
                  a.apellido_paterno LIKE :busqueda OR 
                  a.apellido_materno LIKE :busqueda OR 
                  a.numero_de_boleta LIKE :busqueda)";
    $filtros['busqueda'] = '%' . $filtros['busqueda'] . '%';
}

$sql .= " GROUP BY a.alumno_id";

// Preparar y ejecutar consulta
$stmt = $conn->prepare($sql);

if ($filtros['semestre']) {
    $stmt->bindValue(':semestre', $filtros['semestre'], PDO::PARAM_INT);
}
if ($filtros['turno']) {
    $stmt->bindValue(':turno', $filtros['turno'], PDO::PARAM_INT);
}
if ($filtros['grupo']) {
    $stmt->bindValue(':grupo', $filtros['grupo'], PDO::PARAM_INT);
}
if ($filtros['busqueda']) {
    $stmt->bindValue(':busqueda', $filtros['busqueda'], PDO::PARAM_STR);
}

$stmt->execute();
$alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Aplicar filtro de promedio en PHP (mejor que en SQL para este caso)
if ($filtros['promedio']) {
    $alumnos = array_filter($alumnos, function($alumno) use ($filtros) {
        if ($alumno['promedio_general'] === null) return false;
        
        switch ($filtros['promedio']) {
            case 'alto': return $alumno['promedio_general'] >= 9.0;
            case 'medio': return $alumno['promedio_general'] >= 7.0 && $alumno['promedio_general'] < 9.0;
            case 'bajo': return $alumno['promedio_general'] < 7.0;
            default: return true;
        }
    });
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Alumnos</title>
    <style>
        /* Estilos mejorados */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f7fa;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
        }
        
        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        label {
            margin-bottom: 5px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        select, input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        button, a.button {
            padding: 8px 16px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            font-size: 14px;
        }
        
        #limpiar {
            background: #95a5a6;
        }
        
        button:hover, a.button:hover {
            opacity: 0.9;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        #tabla-alumnos {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        #tabla-alumnos th, #tabla-alumnos td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }
        
        #tabla-alumnos th {
            background: #3498db;
            color: white;
            position: sticky;
            top: 0;
        }
        
        #tabla-alumnos tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        #tabla-alumnos tr:hover {
            background: #e8f4fc;
        }
        
        .promedio-alto {
            color: #27ae60;
            font-weight: bold;
        }
        
        .promedio-medio {
            color: #f39c12;
        }
        
        .promedio-bajo {
            color: #e74c3c;
        }
        
        .details-link {
            color: #3498db;
            text-decoration: none;
        }
        
        .details-link:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .filters {
                grid-template-columns: 1fr;
            }
        }
     
    /* Estilos base mejorados */
    :root {
        --primary-color: #3498db;
        --primary-dark: #2980b9;
        --secondary-color: #2c3e50;
        --accent-color: #e74c3c;
        --light-gray: #ecf0f1;
        --medium-gray: #bdc3c7;
        --dark-gray: #7f8c8d;
        --success-color: #27ae60;
        --warning-color: #f39c12;
        --danger-color: #e74c3c;
        --border-radius: 8px;
        --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        --transition: all 0.3s ease;
    }
    
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }
    
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        line-height: 1.6;
        color: #333;
        background-color: #f8f9fa;
        margin: 0;
        padding: 20px;
        min-height: 100vh;
    }
    
    .container {
        max-width: 1200px;
        margin: 20px auto;
        background-color: white;
        padding: 30px;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
    }
    
    /* Encabezado mejorado */
    .student-header {
        background: linear-gradient(135deg, var(--secondary-color), var(--primary-dark));
        color: white;
        padding: 25px;
        border-radius: var(--border-radius);
        margin-bottom: 30px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        box-shadow: var(--box-shadow);
        position: relative;
        overflow: hidden;
    }
    
    .student-header::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 100%;
        height: 200%;
        background: rgba(255, 255, 255, 0.1);
        transform: rotate(30deg);
    }
    
    .student-title {
        margin: 0;
        font-size: 28px;
        font-weight: 600;
        position: relative;
        z-index: 1;
    }
    
    .student-subtitle {
        font-size: 16px;
        opacity: 0.9;
        margin-top: 5px;
        font-weight: 400;
        position: relative;
        z-index: 1;
    }
    
    /* Sección de información mejorada */
    .student-info-section {
        display: grid;
        grid-template-columns: 250px 1fr;
        gap: 30px;
        margin-bottom: 40px;
    }
    
    .student-photo {
        background-color: var(--light-gray);
        border-radius: var(--border-radius);
        height: 250px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        box-shadow: var(--box-shadow);
        border: 4px solid white;
    }
    
    .student-photo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .student-photo .initials {
        font-size: 72px;
        font-weight: bold;
        color: var(--dark-gray);
    }
    
    .student-details {
        background-color: white;
        border-radius: var(--border-radius);
        padding: 25px;
        box-shadow: var(--box-shadow);
    }
    
    .detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
    }
    
    .detail-item {
        margin-bottom: 15px;
    }
    
    .detail-label {
        font-weight: 600;
        color: var(--dark-gray);
        font-size: 14px;
        margin-bottom: 5px;
        display: flex;
        align-items: center;
    }
    
    .detail-label i {
        margin-right: 8px;
        color: var(--primary-color);
    }
    
    .detail-value {
        font-size: 16px;
        padding: 8px 12px;
        background-color: var(--light-gray);
        border-radius: 4px;
        display: inline-block;
        width: 100%;
    }
    
    /* Sección de estadísticas mejorada */
    .stats-section {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }
    
    .stat-card {
        background-color: white;
        border-radius: var(--border-radius);
        padding: 20px;
        text-align: center;
        box-shadow: var(--box-shadow);
        transition: var(--transition);
        border-top: 4px solid var(--primary-color);
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }
    
    .stat-card.warning {
        border-top-color: var(--warning-color);
    }
    
    .stat-card.success {
        border-top-color: var(--success-color);
    }
    
    .stat-card.danger {
        border-top-color: var(--danger-color);
    }
    
    .stat-value {
        font-size: 36px;
        font-weight: 700;
        color: var(--secondary-color);
        margin: 10px 0;
    }
    
    .stat-card.success .stat-value {
        color: var(--success-color);
    }
    
    .stat-card.warning .stat-value {
        color: var(--warning-color);
    }
    
    .stat-card.danger .stat-value {
        color: var(--danger-color);
    }
    
    .stat-label {
        color: var(--dark-gray);
        font-size: 14px;
        font-weight: 500;
    }
    
    /* Tabla de calificaciones mejorada */
    .grades-section {
        background-color: white;
        border-radius: var(--border-radius);
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: var(--box-shadow);
    }
    
    .section-title {
        color: var(--secondary-color);
        border-bottom: 2px solid var(--primary-color);
        padding-bottom: 10px;
        margin-top: 0;
        margin-bottom: 25px;
        font-size: 22px;
        display: flex;
        align-items: center;
    }
    
    .section-title i {
        margin-right: 10px;
    }
    
    .grades-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    
    .grades-table th, .grades-table td {
        padding: 15px;
        text-align: left;
        border-bottom: 1px solid var(--light-gray);
    }
    
    .grades-table th {
        background-color: var(--primary-color);
        color: white;
        font-weight: 500;
        position: sticky;
        top: 0;
    }
    
    .grades-table tr:nth-child(even) {
        background-color: #f8f9fa;
    }
    
    .grades-table tr:hover {
        background-color: #e8f4fc;
    }
    
    .final-grade {
        font-weight: 600;
        color: var(--secondary-color);
    }
    
    /* Barra de progreso mejorada */
    .progress-container {
        margin-top: 15px;
    }
    
    .progress-label {
        display: flex;
        justify-content: space-between;
        margin-bottom: 5px;
        font-size: 14px;
        color: var(--dark-gray);
    }
    
    .progress-bar {
        height: 10px;
        background-color: var(--light-gray);
        border-radius: 5px;
        overflow: hidden;
    }
    
    .progress {
        height: 100%;
        background: linear-gradient(90deg, var(--primary-color), var(--primary-dark));
        border-radius: 5px;
        transition: width 0.5s ease;
    }
    
    .progress.success {
        background: linear-gradient(90deg, var(--success-color), #2ecc71);
    }
    
    .progress.warning {
        background: linear-gradient(90deg, var(--warning-color), #f1c40f);
    }
    
    .progress.danger {
        background: linear-gradient(90deg, var(--danger-color), #c0392b);
    }
    
    /* Botones mejorados */
    .actions-section {
        display: flex;
        justify-content: flex-end;
        gap: 15px;
        margin-top: 20px;
        flex-wrap: wrap;
    }
    
    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: var(--border-radius);
        cursor: pointer;
        font-weight: 500;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    
    .btn i {
        margin-right: 8px;
    }
    
    .btn-primary {
        background-color: var(--primary-color);
        color: white;
    }
    
    .btn-primary:hover {
        background-color: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    .btn-secondary {
        background-color: var(--medium-gray);
        color: white;
    }
    
    .btn-secondary:hover {
        background-color: var(--dark-gray);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    .btn-success {
        background-color: var(--success-color);
        color: white;
    }
    
    .btn-danger {
        background-color: var(--danger-color);
        color: white;
    }
    
    /* Colores para calificaciones */
    .grade-excellent {
        color: var(--success-color);
        font-weight: 600;
    }
    
    .grade-good {
        color: var(--warning-color);
        font-weight: 600;
    }
    
    .grade-regular {
        color: var(--danger-color);
        font-weight: 600;
    }
    
    /* Badges */
    .badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .badge-primary {
        background-color: var(--primary-color);
        color: white;
    }
    
    .badge-success {
        background-color: var(--success-color);
        color: white;
    }
    
    .badge-warning {
        background-color: var(--warning-color);
        color: white;
    }
    
    .badge-danger {
        background-color: var(--danger-color);
        color: white;
    }
    
    /* Responsive design mejorado */
    @media (max-width: 992px) {
        .student-info-section {
            grid-template-columns: 1fr;
        }
        
        .student-photo {
            width: 200px;
            height: 200px;
            margin: 0 auto;
        }
    }
    
    @media (max-width: 768px) {
        .container {
            padding: 15px;
        }
        
        .detail-grid {
            grid-template-columns: 1fr;
        }
        
        .stats-section {
            grid-template-columns: 1fr 1fr;
        }
        
        .student-header {
            flex-direction: column;
            text-align: center;
            gap: 15px;
        }
        
        .actions-section {
            justify-content: center;
        }
    }
    
    @media (max-width: 576px) {
        .stats-section {
            grid-template-columns: 1fr;
        }
        
        .grades-table {
            display: block;
            overflow-x: auto;
            white-space: nowrap;
        }
        
        .btn {
            width: 100%;
            margin-bottom: 10px;
        }
    }
    
    /* Animaciones */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .animated {
        animation: fadeIn 0.5s ease forwards;
    }
    
    .delay-1 { animation-delay: 0.1s; }
    .delay-2 { animation-delay: 0.2s; }
    .delay-3 { animation-delay: 0.3s; }
    
    /* Scroll personalizado */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }
    
    ::-webkit-scrollbar-track {
        background: var(--light-gray);
    }
    
    ::-webkit-scrollbar-thumb {
        background: var(--primary-color);
        border-radius: 4px;
    }
    
    ::-webkit-scrollbar-thumb:hover {
        background: var(--primary-dark);
    }
</style>


</head>
<body>
    <div class="container">
        <h1>Consulta de Alumnos</h1>
        
        <form method="GET" class="filters">
            <div class="filter-group">
                <label for="semestre">Semestre:</label>
                <select id="semestre" name="semestre">
                    <option value="">Todos</option>
                    <?php foreach ($semestres as $semestre): ?>
                        <option value="<?= $semestre['semestre_id'] ?>" <?= $filtros['semestre'] == $semestre['semestre_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($semestre['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="turno">Turno:</label>
                <select id="turno" name="turno">
                    <option value="">Todos</option>
                    <?php foreach ($turnos as $turno): ?>
                        <option value="<?= $turno['turno_id'] ?>" <?= $filtros['turno'] == $turno['turno_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($turno['nombre']) ?> (<?= substr($turno['hora_inicio'], 0, 5) ?> - <?= substr($turno['hora_fin'], 0, 5) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="grupo">Grupo:</label>
                <select id="grupo" name="grupo">
                    <option value="">Todos</option>
                    <?php foreach ($grupos as $grupo): ?>
                        <option value="<?= $grupo['grupo_id'] ?>" <?= $filtros['grupo'] == $grupo['grupo_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($grupo['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="promedio">Promedio:</label>
                <select id="promedio" name="promedio">
                    <option value="">Todos</option>
                    <option value="alto" <?= $filtros['promedio'] == 'alto' ? 'selected' : '' ?>>9.0 - 10.0</option>
                    <option value="medio" <?= $filtros['promedio'] == 'medio' ? 'selected' : '' ?>>7.0 - 8.9</option>
                    <option value="bajo" <?= $filtros['promedio'] == 'bajo' ? 'selected' : '' ?>>0 - 6.9</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="busqueda">Buscar alumno:</label>
                <input type="text" id="busqueda" name="busqueda" placeholder="Nombre o boleta" 
                       value="<?= $filtros['busqueda'] ? htmlspecialchars($filtros['busqueda']) : '' ?>">
            </div>
            
            <button type="submit" id="buscar">Buscar</button>
            <a href="consultas_alumnos.php" class="button" id="limpiar">Limpiar filtros</a>
        </form>
        
        <div class="table-container">
            <table id="tabla-alumnos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>N° Boleta</th>
                        <th>Nombre del Alumno</th>
                        <th>Semestre</th>
                        <th>Grupo</th>
                        <th>Turno</th>
                        <th>Promedio</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($alumnos) > 0): ?>
                        <?php foreach ($alumnos as $alumno): ?>
                            <?php
                            // Determinar clase CSS para el promedio
                            $clasePromedio = '';
                            if ($alumno['promedio_general'] !== null) {
                                if ($alumno['promedio_general'] >= 9.0) {
                                    $clasePromedio = 'promedio-alto';
                                } elseif ($alumno['promedio_general'] >= 7.0) {
                                    $clasePromedio = 'promedio-medio';
                                } else {
                                    $clasePromedio = 'promedio-bajo';
                                }
                            }
                            ?>
                            <tr>
                                <td><?= $alumno['alumno_id'] ?></td>
                                <td><?= htmlspecialchars($alumno['numero_de_boleta']) ?></td>
                                <td><?= htmlspecialchars(trim($alumno['nombre'] . ' ' . $alumno['apellido_paterno'] . ' ' . ($alumno['apellido_materno'] ?? ''))) ?></td>
                                <td><?= htmlspecialchars($alumno['semestre_nombre']) ?></td>
                                <td><?= htmlspecialchars($alumno['grupo_nombre']) ?></td>
                                <td><?= htmlspecialchars($alumno['turno_nombre']) ?></td>
                                <td class="<?= $clasePromedio ?>">
                                    <?= $alumno['promedio_general'] !== null ? number_format($alumno['promedio_general'], 2) : 'N/A' ?>
                                </td>
                                <td><a href="detalle_alumno.php?id=<?= $alumno['alumno_id'] ?>" class="details-link">Ver detalles</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center;">No se encontraron alumnos con los criterios seleccionados</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Actualizar dinámicamente los grupos cuando cambia semestre o turno
        document.getElementById('semestre').addEventListener('change', actualizarGrupos);
        document.getElementById('turno').addEventListener('change', actualizarGrupos);
        
        function actualizarGrupos() {
            const semestre = document.getElementById('semestre').value;
            const turno = document.getElementById('turno').value;
            
            // En una implementación real, aquí podrías usar AJAX para cargar los grupos dinámicamente
            // Por ahora simplemente recargamos la página con los nuevos parámetros
            const url = new URL(window.location.href);
            if (semestre) url.searchParams.set('semestre', semestre);
            else url.searchParams.delete('semestre');
            
            if (turno) url.searchParams.set('turno', turno);
            else url.searchParams.delete('turno');
            
            // Limpiar grupo seleccionado al cambiar semestre o turno
            url.searchParams.delete('grupo');
            
            window.location.href = url.toString();
        }
    </script>
</body>
</html>