<?php
require_once 'conexion.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de alumno no válido");
}

$alumno_id = intval($_GET['id']);

// Consulta para obtener información del alumno
$sqlAlumno = "SELECT a.*, g.nombre AS grupo_nombre, s.nombre AS semestre_nombre, 
                     t.nombre AS turno_nombre, t.hora_inicio, t.hora_fin,
                     CONCAT(p.nombre, ' ', p.apellido_paterno) AS tutor_nombre
              FROM Alumnos a
              JOIN Grupos g ON a.grupo_id = g.grupo_id
              JOIN Semestres s ON g.semestre_id = s.semestre_id
              JOIN Turnos t ON g.turno_id = t.turno_id
              LEFT JOIN Profesores p ON g.tutor_id = p.profesor_id
              WHERE a.alumno_id = ?";

$stmtAlumno = $conn->prepare($sqlAlumno);
$stmtAlumno->execute([$alumno_id]);
$alumno = $stmtAlumno->fetch(PDO::FETCH_ASSOC);

if (!$alumno) {
    die("Alumno no encontrado");
}

// Calcular edad
$fechaNacimiento = new DateTime($alumno['fecha_nacimiento']);
$hoy = new DateTime();
$edad = $hoy->diff($fechaNacimiento)->y;

// Consulta para obtener los semestres con calificaciones del alumno
$sqlSemestres = "SELECT DISTINCT c.semestre_id, s.nombre 
                 FROM Calificaciones c
                 JOIN Semestres s ON c.semestre_id = s.semestre_id
                 WHERE c.alumno_id = ?
                 ORDER BY s.fecha_inicio DESC";
$stmtSemestres = $conn->prepare($sqlSemestres);
$stmtSemestres->execute([$alumno_id]);
$semestresCalificaciones = $stmtSemestres->fetchAll(PDO::FETCH_ASSOC);

// Obtener calificaciones del semestre actual (o el primero si no se especifica)
$semestreActual = isset($_GET['semestre']) ? intval($_GET['semestre']) : 
                 (count($semestresCalificaciones) > 0 ? $semestresCalificaciones[0]['semestre_id'] : 0);

$calificaciones = [];
$promedioSemestre = null;

if ($semestreActual > 0) {
    // Consulta para calificaciones del semestre seleccionado
    $sqlCalificaciones = "SELECT m.nombre AS materia_nombre, c.parcial1, c.parcial2, c.parcial3, c.final,
                                 CASE WHEN c.final >= 6 THEN 'Aprobado' ELSE 'Reprobado' END AS estatus
                          FROM Calificaciones c
                          JOIN Materias m ON c.materia_id = m.materia_id
                          WHERE c.alumno_id = ? AND c.semestre_id = ?";
    $stmtCalificaciones = $conn->prepare($sqlCalificaciones);
    $stmtCalificaciones->execute([$alumno_id, $semestreActual]);
    $calificaciones = $stmtCalificaciones->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular promedio del semestre
    $sqlPromedio = "SELECT AVG(final) AS promedio 
                    FROM Calificaciones 
                    WHERE alumno_id = ? AND semestre_id = ?";
    $stmtPromedio = $conn->prepare($sqlPromedio);
    $stmtPromedio->execute([$alumno_id, $semestreActual]);
    $promedioSemestre = $stmtPromedio->fetch(PDO::FETCH_ASSOC)['promedio'];
}

// Consulta para información de contacto (usando datos del alumno)
// En una aplicación real podrías tener una tabla separada para contactos de emergencia
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles del Alumno - <?= htmlspecialchars($alumno['nombre']) ?></title>
    <style>
       
    /* Estilos generales */
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    line-height: 1.6;
    color: #333;
    background-color: #f5f5f5;
    margin: 0;
    padding: 0;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

/* Encabezado */
.student-header {
    background-color: #2c3e50;
    color: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.student-title {
    margin: 0;
    font-size: 28px;
}

.student-id {
    font-size: 16px;
    opacity: 0.9;
}

/* Sección de información básica */
.student-info-section {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 30px;
    margin-bottom: 30px;
}

.student-photo {
    background-color: #ecf0f1;
    border-radius: 8px;
    height: 250px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.student-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.student-details {
    background-color: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.detail-item {
    margin-bottom: 10px;
}

.detail-label {
    font-weight: bold;
    color: #7f8c8d;
    font-size: 14px;
    margin-bottom: 5px;
}

.detail-value {
    font-size: 16px;
}

/* Sección de calificaciones */
.grades-section {
    background-color: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.section-title {
    color: #2c3e50;
    border-bottom: 2px solid #3498db;
    padding-bottom: 10px;
    margin-top: 0;
    margin-bottom: 20px;
}

.grades-table {
    width: 100%;
    border-collapse: collapse;
}

.grades-table th, .grades-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #ecf0f1;
}

.grades-table th {
    background-color: #3498db;
    color: white;
    font-weight: 500;
}

.grades-table tr:nth-child(even) {
    background-color: #f8f9fa;
}

.grades-table tr:hover {
    background-color: #e8f4fc;
}

.final-grade {
    font-weight: bold;
    color: #2c3e50;
}

/* Sección de estadísticas */
.stats-section {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background-color: white;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.stat-value {
    font-size: 32px;
    font-weight: bold;
    color: #3498db;
    margin: 10px 0;
}

.stat-label {
    color: #7f8c8d;
    font-size: 14px;
}

/* Botones y acciones */
.actions-section {
    display: flex;
    justify-content: flex-end;
    gap: 15px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-primary {
    background-color: #3498db;
    color: white;
}

.btn-primary:hover {
    background-color: #2980b9;
}

.btn-secondary {
    background-color: #95a5a6;
    color: white;
}

.btn-secondary:hover {
    background-color: #7f8c8d;
}

/* Responsive design */
@media (max-width: 768px) {
    .student-info-section {
        grid-template-columns: 1fr;
    }
    
    .detail-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-section {
        grid-template-columns: 1fr;
    }
    
    .grades-table {
        display: block;
        overflow-x: auto;
    }
}

/* Colores para diferentes estados de calificación */
.grade-excellent {
    color: #27ae60;
    font-weight: bold;
}

.grade-good {
    color: #f39c12;
}

.grade-regular {
    color: #e74c3c;
}

/* Gráficos o elementos visuales */
.progress-bar {
    height: 8px;
    background-color: #ecf0f1;
    border-radius: 4px;
    margin-top: 10px;
    overflow: hidden;
}

.progress {
    height: 100%;
    background-color: #3498db;
    border-radius: 4px;
}


    </style>
</head>
<body>
    <div class="container">
        <a href="consultas_alumnos.php" class="back-link">← Volver a la consulta de alumnos</a>
        
        <h1>Detalles del Alumno</h1>
        
        <div class="student-info">
            <div class="photo">FOTO DEL ALUMNO</div>
            <div class="details">
                <div class="detail-row">
                    <div class="detail-label">N° Boleta:</div>
                    <div class="detail-value"><?= htmlspecialchars($alumno['numero_de_boleta']) ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Nombre completo:</div>
                    <div class="detail-value">
                        <?= htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellido_paterno'] . ' ' . $alumno['apellido_materno']) ?>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Fecha de nacimiento:</div>
                    <div class="detail-value">
                        <?= date('d/m/Y', strtotime($alumno['fecha_nacimiento'])) ?>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Edad:</div>
                    <div class="detail-value"><?= $edad ?> años</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Grupo:</div>
                    <div class="detail-value">
                        <?= htmlspecialchars($alumno['grupo_nombre'] . ' - ' . $alumno['semestre_nombre']) ?>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Turno:</div>
                    <div class="detail-value">
                        <?= htmlspecialchars($alumno['turno_nombre']) ?> 
                        (<?= substr($alumno['hora_inicio'], 0, 5) ?> - <?= substr($alumno['hora_fin'], 0, 5) ?>)
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Tutor del grupo:</div>
                    <div class="detail-value">
                        <?= $alumno['tutor_nombre'] ? htmlspecialchars($alumno['tutor_nombre']) : 'No asignado' ?>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Estatus:</div>
                    <div class="detail-value">
                        <?= $alumno['activo'] ? 'Activo' : 'Inactivo' ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="tabs">
            <div class="tab active" data-tab="calificaciones">Calificaciones</div>
            <div class="tab" data-tab="contacto">Contacto</div>
        </div>
        
        <div class="tab-content active" id="calificaciones">
            <h2 class="section-title">Calificaciones por Semestre</h2>
            
            <?php if (count($semestresCalificaciones) > 0): ?>
            <div class="filter-group">
                <label for="semestre-calificaciones">Seleccionar semestre:</label>
                <select id="semestre-calificaciones">
                    <?php foreach ($semestresCalificaciones as $semestre): ?>
                        <option value="<?= $semestre['semestre_id'] ?>" 
                            <?= $semestre['semestre_id'] == $semestreActual ? 'selected' : '' ?>>
                            <?= htmlspecialchars($semestre['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Materia</th>
                        <th>Parcial 1</th>
                        <th>Parcial 2</th>
                        <th>Parcial 3</th>
                        <th>Final</th>
                        <th>Estatus</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($calificaciones as $calificacion): 
                        $claseFinal = '';
                        if ($calificacion['final'] >= 9.0) {
                            $claseFinal = 'promedio-alto';
                        } elseif ($calificacion['final'] >= 7.0) {
                            $claseFinal = 'promedio-medio';
                        } else {
                            $claseFinal = 'promedio-bajo';
                        }
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($calificacion['materia_nombre']) ?></td>
                        <td><?= $calificacion['parcial1'] !== null ? number_format($calificacion['parcial1'], 2) : 'N/A' ?></td>
                        <td><?= $calificacion['parcial2'] !== null ? number_format($calificacion['parcial2'], 2) : 'N/A' ?></td>
                        <td><?= $calificacion['parcial3'] !== null ? number_format($calificacion['parcial3'], 2) : 'N/A' ?></td>
                        <td class="<?= $claseFinal ?>">
                            <?= $calificacion['final'] !== null ? number_format($calificacion['final'], 2) : 'N/A' ?>
                        </td>
                        <td><?= $calificacion['estatus'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="summary">
                <h3>Resumen académico</h3>
                <p><strong>Promedio del semestre:</strong> 
                    <span class="<?= $promedioSemestre >= 9.0 ? 'promedio-alto' : ($promedioSemestre >= 7.0 ? 'promedio-medio' : 'promedio-bajo') ?>">
                        <?= $promedioSemestre !== null ? number_format($promedioSemestre, 2) : 'N/A' ?>
                    </span>
                </p>
                <p><strong>Materias aprobadas:</strong> 
                    <?= array_reduce($calificaciones, function($carry, $item) {
                        return $carry + ($item['final'] >= 6 ? 1 : 0);
                    }, 0) ?>
                </p>
                <p><strong>Materias reprobadas:</strong> 
                    <?= array_reduce($calificaciones, function($carry, $item) {
                        return $carry + ($item['final'] < 6 ? 1 : 0);
                    }, 0) ?>
                </p>
            </div>
            <?php else: ?>
            <p>El alumno no tiene calificaciones registradas.</p>
            <?php endif; ?>
        </div>
        
        <div class="tab-content" id="contacto">
            <h2 class="section-title">Información de Contacto</h2>
            <div class="detail-row">
                <div class="detail-label">Dirección:</div>
                <div class="detail-value"><?= htmlspecialchars($alumno['direccion']) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Teléfono:</div>
                <div class="detail-value"><?= htmlspecialchars($alumno['telefono']) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Email:</div>
                <div class="detail-value"><?= htmlspecialchars($alumno['email']) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">CURP:</div>
                <div class="detail-value"><?= htmlspecialchars($alumno['curp']) ?></div>
            </div>
        </div>
    </div>

    <script>
        // Funcionalidad de las pestañas
        const tabs = document.querySelectorAll('.tab');
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                tab.classList.add('active');
                const tabId = tab.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
            });
        });
        
        // Cambiar semestre para calificaciones
        document.getElementById('semestre-calificaciones').addEventListener('change', function() {
            const url = new URL(window.location.href);
            url.searchParams.set('semestre', this.value);
            window.location.href = url.toString();
        });
    </script>
</body>
</html>