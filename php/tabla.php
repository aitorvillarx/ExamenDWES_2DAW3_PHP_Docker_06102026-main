<html>
    <head>
        <title>Examen de Desarrollo web en entorno servidor</title>
        <link rel="icon" type="image/png" sizes="32x32" href="../imagenes/favicon.jpeg">
        <link rel="stylesheet" type="text/css" href="../estilos/estilos.css">
    </head>
    <body>
        <h1>Centro de Ayuda al Empleo</h1>
        <h2>    
            <?php
                // En los enlaces se recibe "tipo" y en el formulario "profesion".
                $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
                if ($metodo === 'POST') {
                    $tipoRecibido = $_POST['profesion'] ?? '';
                } else {
                    $tipoRecibido = $_GET['tipo'] ?? 'soldadura';
                }
                $tipo = "Soldadura";
                if ($tipoRecibido == 'informatica'){
                    $tipo = 'Informática';
                } elseif ($tipoRecibido == 'socio'){
                    $tipo = "Asistencia Sociosanitaria";
                }
                echo "Solicitudes de $tipo";
            ?>
        </h2>

        <?php
        // Estos son los únicos valores de profesión que acepta el formulario.
        $profesiones = ['soldadura', 'informatica', 'socio'];

        if (!in_array($tipoRecibido, $profesiones, true)) {
            http_response_code(400);
            echo '<p>La profesión seleccionada no es válida.</p>';
            echo '<button onclick="location.href=\'../html/index.html\'">Volver al formulario</button>';
            exit;
        }

        // Se reutiliza el valor del formulario para filtrar la tabla.
        $profesion = $tipoRecibido;
        $errores = [];

        // Si se ha enviado el formulario, revisamos los datos y guardamos la solicitud.
        if ($metodo === 'POST') {
            $nombre = trim($_POST['nombre'] ?? '');
            $apellidos = trim($_POST['apellidos'] ?? '');
            $dni = trim($_POST['dni'] ?? '');
            $f_nac = $_POST['f_nac'] ?? '';
            $tlf = trim($_POST['tlf'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $jornadaParcial = $_POST['jornadaParcial'] ?? '';
            $idiomasRecibidos = $_POST['idiomas'] ?? [];

            if ($nombre === '' || $apellidos === '' || $dni === '' || $f_nac === '' || $tlf === '') {
                $errores[] = 'Completa todos los campos personales.';
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errores[] = 'Introduce un email válido.';
            }

            if (!in_array($jornadaParcial, ['0', '1'], true)) {
                $errores[] = 'Selecciona una jornada parcial o completa.';
            }

            // Una fecha de formulario válida tiene formato AAAA-MM-DD.
            $fechaValida = DateTime::createFromFormat('Y-m-d', $f_nac);
            if (!$fechaValida || $fechaValida->format('Y-m-d') !== $f_nac) {
                $errores[] = 'Introduce una fecha de nacimiento válida.';
            }

            if (!is_array($idiomasRecibidos)) {
                $idiomasRecibidos = [];
            }
            $idiomasPermitidos = ['Euskera', 'Inglés'];
            $idiomasRecibidos = array_filter($idiomasRecibidos, 'is_string');
            $idiomasElegidos = array_values(array_intersect($idiomasPermitidos, $idiomasRecibidos));
            $idiomas = implode(', ', $idiomasElegidos);

            if (!$errores) {
                try {
                    // XAMPP suele usar el usuario root sin contraseña en local.
                    $conexion = new PDO(
                        'mysql:host=localhost;dbname=CAE;charset=utf8mb4',
                        'root',
                        ''
                    );
                    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    // Las consultas preparadas guardan los datos de forma segura.
                    $consulta = $conexion->prepare(
                        'INSERT INTO SOLICITUD
                        (nombre, apellidos, dni, f_nac, tlf, email, profesion, jornadaParcial, idiomas)
                        VALUES
                        (:nombre, :apellidos, :dni, :f_nac, :tlf, :email, :profesion, :jornadaParcial, :idiomas)'
                    );
                    $consulta->execute([
                        ':nombre' => $nombre,
                        ':apellidos' => $apellidos,
                        ':dni' => $dni,
                        ':f_nac' => $f_nac,
                        ':tlf' => $tlf,
                        ':email' => $email,
                        ':profesion' => $profesion,
                        ':jornadaParcial' => $jornadaParcial,
                        ':idiomas' => $idiomas
                    ]);

                    echo '<p>La solicitud se ha guardado correctamente.</p>';
                } catch (PDOException $error) {
                    http_response_code(500);
                    echo '<p>No se pudo guardar la solicitud. Comprueba que MySQL está iniciado en XAMPP y que has creado la base de datos CAE.</p>';
                    echo '<p>Detalle: ' . htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
                }
            }
        }

        if ($errores) {
            echo '<ul>';
            foreach ($errores as $error) {
                echo '<li>' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            echo '</ul>';
        }

        try {
            // Conexión para consultar las solicitudes de la profesión elegida.
            $conexion = new PDO(
                'mysql:host=localhost;dbname=CAE;charset=utf8mb4',
                'root',
                ''
            );
            $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $consulta = $conexion->prepare(
                'SELECT id, nombre, apellidos, dni, f_nac, tlf, email, profesion, jornadaParcial, idiomas
                 FROM SOLICITUD
                 WHERE profesion = :profesion
                 ORDER BY id DESC'
            );
            $consulta->execute([':profesion' => $profesion]);
            $solicitudes = $consulta->fetchAll(PDO::FETCH_ASSOC);

            // Escapamos los valores antes de mostrarlos en HTML.
            function escapar($valor) {
                return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
            }
            ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Apellidos</th>
                        <th>DNI</th>
                        <th>Fecha de nacimiento</th>
                        <th>Teléfono</th>
                        <th>Email</th>
                        <th>Profesión</th>
                        <th>Jornada</th>
                        <th>Idiomas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$solicitudes) { ?>
                        <tr>
                            <td colspan="10">Todavía no hay solicitudes para esta profesión.</td>
                        </tr>
                    <?php } ?>
                    <?php foreach ($solicitudes as $solicitud) { ?>
                        <tr>
                            <td><?= escapar($solicitud['id']) ?></td>
                            <td><?= escapar($solicitud['nombre']) ?></td>
                            <td><?= escapar($solicitud['apellidos']) ?></td>
                            <td><?= escapar($solicitud['dni']) ?></td>
                            <td><?= escapar($solicitud['f_nac']) ?></td>
                            <td><?= escapar($solicitud['tlf']) ?></td>
                            <td><?= escapar($solicitud['email']) ?></td>
                            <td><?= escapar($solicitud['profesion']) ?></td>
                            <td><?= $solicitud['jornadaParcial'] ? 'Parcial' : 'Completa' ?></td>
                            <td><?= escapar($solicitud['idiomas']) ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php
        } catch (PDOException $error) {
            http_response_code(500);
            echo '<p>No se pudieron consultar las solicitudes. Comprueba que MySQL está iniciado en XAMPP y que has creado la base de datos CAE.</p>';
            echo '<p>Detalle: ' . htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
        }
        ?>
        <button onclick="location.href='../html/index.html'">Volver al formulario</button>
    </body>
</html>