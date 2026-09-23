<?php
// functions.php
// Archivo central de funciones auxiliares para el sistema ANRS Trámites

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Asegurar que la conexión PDO esté disponible globalmente
global $pdo;
if (!isset($pdo)) {
    require_once __DIR__ . '/config.php';
}

// ============================================================
// 1. FUNCIONES DE SEGURIDAD Y PERMISOS
// ============================================================

/**
 * Verifica si el usuario actual tiene el rol de Administrador (id_rol = 3)
 * @return bool
 */
function esAdmin() {
    return in_array(3, $_SESSION['roles'] ?? []);
}

/**
 * Verifica si el usuario actual tiene un rol específico
 * @param int $rol_id
 * @return bool
 */
function tieneRol($rol_id) {
    return in_array($rol_id, $_SESSION['roles'] ?? []);
}

/**
 * Obtiene los IDs de los titulares a los que el usuario tiene acceso.
 * Si es administrador, devuelve null (significa "todos").
 * @return array|null
 */
function getTitularesUsuario() {
    if (esAdmin()) {
        return null; // acceso total
    }
    return $_SESSION['titulares'] ?? [];
}

/**
 * Obtiene el nombre de un titular por ID
 * @param int $id
 * @return string
 */
function getTitularNombre($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT nombre FROM titular WHERE id_titular = :id");
    $stmt->execute([':id' => $id]);
    $nombre = $stmt->fetchColumn();
    return $nombre ?: 'Titular #' . $id;
}
/**
 * Verifica si el usuario tiene acceso a un titular específico
 * @param int $id_titular
 * @return bool
 */
function tieneAccesoATitular($id_titular) {
    if (esAdmin()) {
        return true;
    }
    return in_array($id_titular, $_SESSION['titulares'] ?? []);
}

/**
 * Redirige si el usuario no está autenticado
 */
function requiereAutenticacion() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Redirige si el usuario no tiene el rol especificado
 * @param int $rol_id
 */
function requiereRol($rol_id) {
    if (!tieneRol($rol_id)) {
        header('Location: dashboard.php?error=no_autorizado');
        exit;
    }
}

/**
 * Redirige si el usuario no es administrador
 */
function requiereAdmin() {
    if (!esAdmin()) {
        header('Location: dashboard.php?error=no_autorizado');
        exit;
    }
}

// ============================================================
// 2. FUNCIONES PARA OBTENER DATOS DE CATÁLOGOS BASE
// ============================================================

/**
 * Obtiene todos los departamentos
 * @return array
 */
function obtenerDepartamentos() {
    global $pdo;
    $stmt = $pdo->query("SELECT id_departamento, nombre FROM departamento ORDER BY nombre");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene municipios de un departamento específico
 * @param int $id_departamento
 * @return array
 */
function obtenerMunicipiosPorDepartamento($id_departamento) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id_municipio, nombre FROM municipio WHERE id_departamento = :id ORDER BY nombre");
    $stmt->execute([':id' => $id_departamento]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene todas las direcciones ANRS
 * @return array
 */
function obtenerDireccionesANRS() {
    global $pdo;
    $stmt = $pdo->query("SELECT id_direccion_anrs, nombre FROM direccion_anrs ORDER BY nombre");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene el nombre de una dirección ANRS por ID
 * @param int $id
 * @return string|null
 */
function getDireccionANRSNombre($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT nombre FROM direccion_anrs WHERE id_direccion_anrs = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetchColumn();
}

/**
 * Obtiene todos los tipos de licencia (sin filtro)
 * @return array
 */
function obtenerTiposLicencia() {
    global $pdo;
    $stmt = $pdo->query("SELECT id_tipo_licencia, nombre FROM tipo_licencia ORDER BY nombre");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene los tipos de licencia filtrados por dirección ANRS
 * @param int $id_direccion_anrs
 * @return array
 */
function obtenerTiposLicenciaPorDireccion($id_direccion_anrs) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id_tipo_licencia, nombre FROM tipo_licencia WHERE id_direccion_anrs = :id ORDER BY nombre");
    $stmt->execute([':id' => $id_direccion_anrs]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene todos los tipos de trámite
 * @return array
 */
function obtenerTiposTramite() {
    global $pdo;
    $stmt = $pdo->query("SELECT id_tipo_tramite, nombre FROM tipo_tramite ORDER BY nombre");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene todos los tipos de modificación
 * @return array
 */
function obtenerTiposModificacion() {
    global $pdo;
    $stmt = $pdo->query("SELECT id_tipo_modificacion, nombre FROM tipo_modificacion ORDER BY nombre");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene todos los tipos de documento
 * @return array
 */
function obtenerTiposDocumento() {
    global $pdo;
    $stmt = $pdo->query("SELECT id_tipo_documento, nombre FROM tipo_documento ORDER BY nombre");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene todos los países
 * @return array
 */
function obtenerPaises() {
    global $pdo;
    $stmt = $pdo->query("SELECT id_pais, nombre, codigo FROM pais ORDER BY nombre");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene el ID de Nicaragua por su código 'NI'
 * @return int|null
 */
function obtenerIdNicaragua() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id_pais FROM pais WHERE codigo = 'NI'");
    $stmt->execute();
    return $stmt->fetchColumn();
}

// ============================================================
// 3. FUNCIONES PARA DISTRIBUIDORES (con búsqueda y paginación)
// ============================================================

/**
 * Obtiene un distribuidor por ID
 * @param int $id
 * @return array|null
 */
function obtenerDistribuidor($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM distribuidor WHERE id_distribuidor = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Obtiene todos los distribuidores (sin filtro, para usos internos)
 * @return array
 */
function obtenerDistribuidores() {
    global $pdo;
    $stmt = $pdo->query("
        SELECT d.*, 
               dep.nombre AS departamento_nombre,
               mun.nombre AS municipio_nombre,
               tl.nombre AS tipo_licencia_nombre,
               dir.nombre AS direccion_anrs_nombre
        FROM distribuidor d
        LEFT JOIN departamento dep ON d.id_departamento = dep.id_departamento
        LEFT JOIN municipio mun ON d.id_municipio = mun.id_municipio
        LEFT JOIN tipo_licencia tl ON d.id_tipo_licencia = tl.id_tipo_licencia
        LEFT JOIN direccion_anrs dir ON d.id_direccion_anrs = dir.id_direccion_anrs
        ORDER BY d.nombre
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Busca distribuidores con paginación
 * @param string $busqueda
 * @param int $pagina
 * @param int $limite
 * @return array
 */
function buscarDistribuidores($busqueda = '', $pagina = 1, $limite = 10) {
    global $pdo;
    $offset = ($pagina - 1) * $limite;
    $params = [];
    $where = "";
    if (!empty($busqueda)) {
        $where = " WHERE d.nombre LIKE :busqueda 
                   OR d.numero_licencia LIKE :busqueda 
                   OR dep.nombre LIKE :busqueda
                   OR mun.nombre LIKE :busqueda
                   OR tl.nombre LIKE :busqueda
                   OR dir.nombre LIKE :busqueda";
        $params[':busqueda'] = "%$busqueda%";
    }

    $sqlCount = "SELECT COUNT(*) as total 
                 FROM distribuidor d
                 LEFT JOIN departamento dep ON d.id_departamento = dep.id_departamento
                 LEFT JOIN municipio mun ON d.id_municipio = mun.id_municipio
                 LEFT JOIN tipo_licencia tl ON d.id_tipo_licencia = tl.id_tipo_licencia
                 LEFT JOIN direccion_anrs dir ON d.id_direccion_anrs = dir.id_direccion_anrs
                 $where";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = $stmtCount->fetchColumn();

    $sqlData = "
        SELECT d.*, 
               dep.nombre AS departamento_nombre,
               mun.nombre AS municipio_nombre,
               tl.nombre AS tipo_licencia_nombre,
               dir.nombre AS direccion_anrs_nombre
        FROM distribuidor d
        LEFT JOIN departamento dep ON d.id_departamento = dep.id_departamento
        LEFT JOIN municipio mun ON d.id_municipio = mun.id_municipio
        LEFT JOIN tipo_licencia tl ON d.id_tipo_licencia = tl.id_tipo_licencia
        LEFT JOIN direccion_anrs dir ON d.id_direccion_anrs = dir.id_direccion_anrs
        $where
        ORDER BY d.nombre
        LIMIT :limite OFFSET :offset
    ";
    $stmtData = $pdo->prepare($sqlData);
    foreach ($params as $key => $value) {
        $stmtData->bindValue($key, $value);
    }
    $stmtData->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();
    $datos = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    return ['datos' => $datos, 'total' => $total];
}

// ============================================================
// 4. FUNCIONES PARA TITULARES (con búsqueda y paginación)
// ============================================================

function obtenerTitular($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM titular WHERE id_titular = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function obtenerTitulares() {
    global $pdo;
    $stmt = $pdo->query("
        SELECT t.*, p.nombre AS pais_nombre
        FROM titular t
        LEFT JOIN pais p ON t.id_pais = p.id_pais
        ORDER BY t.nombre
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buscarTitulares($busqueda = '', $pagina = 1, $limite = 10) {
    global $pdo;
    $offset = ($pagina - 1) * $limite;
    $params = [];
    $where = "";
    if (!empty($busqueda)) {
        $where = " WHERE t.nombre LIKE :busqueda 
                   OR t.direccion_texto LIKE :busqueda
                   OR t.telefono LIKE :busqueda
                   OR t.correo LIKE :busqueda
                   OR p.nombre LIKE :busqueda";
        $params[':busqueda'] = "%$busqueda%";
    }

    $sqlCount = "SELECT COUNT(*) as total 
                 FROM titular t
                 LEFT JOIN pais p ON t.id_pais = p.id_pais
                 $where";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = $stmtCount->fetchColumn();

    $sqlData = "
        SELECT t.*, p.nombre AS pais_nombre
        FROM titular t
        LEFT JOIN pais p ON t.id_pais = p.id_pais
        $where
        ORDER BY t.nombre
        LIMIT :limite OFFSET :offset
    ";
    $stmtData = $pdo->prepare($sqlData);
    foreach ($params as $key => $value) {
        $stmtData->bindValue($key, $value);
    }
    $stmtData->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();
    $datos = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    return ['datos' => $datos, 'total' => $total];
}

// ============================================================
// 5. FUNCIONES PARA PERSONAS (con búsqueda y paginación)
// ============================================================

function obtenerPersona($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM personas WHERE id_persona = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function obtenerPersonas() {
    global $pdo;
    $stmt = $pdo->query("
        SELECT p.*,
               ti.nombre AS tipo_identificacion_nombre,
               pais.nombre AS pais_nombre,
               dep.nombre AS departamento_nombre,
               mun.nombre AS municipio_nombre
        FROM personas p
        LEFT JOIN tipo_identificacion ti ON p.id_tipo_identificacion = ti.id_tipo_identificacion
        LEFT JOIN pais pais ON p.id_pais = pais.id_pais
        LEFT JOIN departamento dep ON p.id_departamento = dep.id_departamento
        LEFT JOIN municipio mun ON p.id_municipio = mun.id_municipio
        ORDER BY p.nombre
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buscarPersonas($busqueda = '', $pagina = 1, $limite = 10) {
    global $pdo;
    $offset = ($pagina - 1) * $limite;
    $params = [];
    $where = "";
   if (!empty($busqueda)) {
    $where = " WHERE p.nombre LIKE :busqueda 
               OR p.numero_identificacion LIKE :busqueda 
               OR p.direccion LIKE :busqueda
               OR p.telefono LIKE :busqueda
               OR p.correo LIKE :busqueda
               OR ti.nombre LIKE :busqueda
               OR pais.nombre LIKE :busqueda
               OR dep.nombre LIKE :busqueda
               OR mun.nombre LIKE :busqueda";
    $params[':busqueda'] = "%$busqueda%";
}
    $sqlCount = "SELECT COUNT(*) as total 
                 FROM personas p
                 LEFT JOIN tipo_identificacion ti ON p.id_tipo_identificacion = ti.id_tipo_identificacion
                 LEFT JOIN pais pais ON p.id_pais = pais.id_pais
                 LEFT JOIN departamento dep ON p.id_departamento = dep.id_departamento
                 LEFT JOIN municipio mun ON p.id_municipio = mun.id_municipio
                 $where";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = $stmtCount->fetchColumn();

    $sqlData = "
        SELECT p.*,
               ti.nombre AS tipo_identificacion_nombre,
               pais.nombre AS pais_nombre,
               dep.nombre AS departamento_nombre,
               mun.nombre AS municipio_nombre
        FROM personas p
        LEFT JOIN tipo_identificacion ti ON p.id_tipo_identificacion = ti.id_tipo_identificacion
        LEFT JOIN pais pais ON p.id_pais = pais.id_pais
        LEFT JOIN departamento dep ON p.id_departamento = dep.id_departamento
        LEFT JOIN municipio mun ON p.id_municipio = mun.id_municipio
        $where
        ORDER BY p.nombre
        LIMIT :limite OFFSET :offset
    ";
    $stmtData = $pdo->prepare($sqlData);
    foreach ($params as $key => $value) {
        $stmtData->bindValue($key, $value);
    }
    $stmtData->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();
    $datos = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    return ['datos' => $datos, 'total' => $total];
}

function obtenerTiposIdentificacion() {
    global $pdo;
    $stmt = $pdo->query("SELECT id_tipo_identificacion, nombre FROM tipo_identificacion ORDER BY nombre");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================================
// 6. FUNCIONES PARA FABRICANTES (con búsqueda y paginación)
// ============================================================

function obtenerFabricantes() {
    global $pdo;
    $stmt = $pdo->query("
        SELECT f.*, p.nombre AS pais_nombre
        FROM fabricante f
        LEFT JOIN pais p ON f.id_pais = p.id_pais
        ORDER BY f.nombre
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerFabricante($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM fabricante WHERE id_fabricante = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function buscarFabricantes($busqueda = '', $pagina = 1, $limite = 10) {
    global $pdo;
    $offset = ($pagina - 1) * $limite;
    $params = [];
    $where = "";
    if (!empty($busqueda)) {
        $where = " WHERE f.nombre LIKE :busqueda 
                   OR f.direccion_texto LIKE :busqueda
                   OR f.telefono LIKE :busqueda
                   OR f.correo LIKE :busqueda
                   OR p.nombre LIKE :busqueda";
        $params[':busqueda'] = "%$busqueda%";
    }

    $sqlCount = "SELECT COUNT(*) as total 
                 FROM fabricante f
                 LEFT JOIN pais p ON f.id_pais = p.id_pais
                 $where";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = $stmtCount->fetchColumn();

    $sqlData = "
        SELECT f.*, p.nombre AS pais_nombre
        FROM fabricante f
        LEFT JOIN pais p ON f.id_pais = p.id_pais
        $where
        ORDER BY f.nombre
        LIMIT :limite OFFSET :offset
    ";
    $stmtData = $pdo->prepare($sqlData);
    foreach ($params as $key => $value) {
        $stmtData->bindValue($key, $value);
    }
    $stmtData->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();
    $datos = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    return ['datos' => $datos, 'total' => $total];
}

// ============================================================
// 7. FUNCIONES PARA PRODUCTOS (con búsqueda y paginación)
// ============================================================

function calcularEstadoProducto($producto) {
    if (empty($producto['fecha_vencimiento_registro'])) {
        return $producto['estado_producto'] ?? 'Activo';
    }
    $fecha_vencimiento = strtotime($producto['fecha_vencimiento_registro']);
    $fecha_actual = time();
    if ($fecha_vencimiento < $fecha_actual) {
        return 'Vencido';
    }
    return $producto['estado_producto'] ?? 'Activo';
}

function obtenerProducto($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM producto WHERE id_producto = :id");
    $stmt->execute([':id' => $id]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($producto) {
        $producto['estado_calculado'] = calcularEstadoProducto($producto);
    }
    return $producto;
}

function obtenerProductos() {
    global $pdo;
    $stmt = $pdo->query("
        SELECT p.*, f.nombre AS fabricante_nombre
        FROM producto p
        LEFT JOIN fabricante f ON p.id_fabricante = f.id_fabricante
        ORDER BY p.nombre
    ");
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($productos as &$producto) {
        $producto['estado_calculado'] = calcularEstadoProducto($producto);
    }
    return $productos;
}

function buscarProductos($busqueda = '', $pagina = 1, $limite = 10) {
    global $pdo;
    $offset = ($pagina - 1) * $limite;
    $params = [];
    $where = "";
    if (!empty($busqueda)) {
        $where = " WHERE p.nombre LIKE :busqueda 
                   OR p.numero_registro LIKE :busqueda
                   OR p.marca LIKE :busqueda
                   OR f.nombre LIKE :busqueda";
        $params[':busqueda'] = "%$busqueda%";
    }

    $sqlCount = "SELECT COUNT(*) as total 
                 FROM producto p
                 LEFT JOIN fabricante f ON p.id_fabricante = f.id_fabricante
                 $where";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = $stmtCount->fetchColumn();

    $sqlData = "
        SELECT p.*, f.nombre AS fabricante_nombre
        FROM producto p
        LEFT JOIN fabricante f ON p.id_fabricante = f.id_fabricante
        $where
        ORDER BY p.nombre
        LIMIT :limite OFFSET :offset
    ";
    $stmtData = $pdo->prepare($sqlData);
    foreach ($params as $key => $value) {
        $stmtData->bindValue($key, $value);
    }
    $stmtData->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();
    $datos = $stmtData->fetchAll(PDO::FETCH_ASSOC);
    foreach ($datos as &$producto) {
        $producto['estado_calculado'] = calcularEstadoProducto($producto);
    }
    return ['datos' => $datos, 'total' => $total];
}

function verificarDependenciasProducto($id_producto) {
    global $pdo;
    $dependencias = [];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM solicitud WHERE id_producto = :id");
    $stmt->execute([':id' => $id_producto]);
    if ($stmt->fetchColumn() > 0) {
        $dependencias[] = 'Solicitud';
    }
    return $dependencias;
}

// ============================================================
// 8. FUNCIONES PARA SOLICITUDES (ejemplo básico sin paginación aquí)
// ============================================================

function obtenerSolicitudesAccesibles() {
    global $pdo;
    if (esAdmin()) {
        $stmt = $pdo->query("SELECT s.*, t.nombre AS titular_nombre, p.nombre AS producto_nombre 
                             FROM solicitud s
                             LEFT JOIN titular t ON s.id_titular = t.id_titular
                             LEFT JOIN producto p ON s.id_producto = p.id_producto
                             ORDER BY s.fecha_solicita DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $titulares = $_SESSION['titulares'] ?? [];
        if (empty($titulares)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($titulares), '?'));
        $sql = "SELECT s.*, t.nombre AS titular_nombre, p.nombre AS producto_nombre 
                FROM solicitud s
                LEFT JOIN titular t ON s.id_titular = t.id_titular
                LEFT JOIN producto p ON s.id_producto = p.id_producto
                WHERE s.id_titular IN ($placeholders)
                ORDER BY s.fecha_solicita DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($titulares);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

function obtenerSolicitud($id_solicitud) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM solicitud WHERE id_solicitud = :id");
    $stmt->execute([':id' => $id_solicitud]);
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$solicitud) return null;
    if (!esAdmin() && !tieneAccesoATitular($solicitud['id_titular'])) return null;
    return $solicitud;
}

// ============================================================
// 9. FUNCIONES PARA USUARIOS (con búsqueda y paginación)
// ============================================================

function obtenerUsuarios() {
    global $pdo;
    $stmt = $pdo->query("
        SELECT u.id_usuario, u.id_persona, u.nombre_usuario, u.activo, 
               p.nombre AS persona_nombre, p.numero_identificacion
        FROM usuario u
        LEFT JOIN personas p ON u.id_persona = p.id_persona
        ORDER BY u.nombre_usuario
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerUsuario($id_user) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT u.id_usuario, u.id_persona, u.nombre_usuario, u.activo, 
               p.nombre AS persona_nombre
        FROM usuario u
        LEFT JOIN personas p ON u.id_persona = p.id_persona
        WHERE u.id_usuario = :id_user
    ");
    $stmt->execute([':id_user' => $id_user]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function buscarUsuarios($busqueda = '', $pagina = 1, $limite = 10) {
    global $pdo;
    $offset = ($pagina - 1) * $limite;
    $params = [];
    $where = "";
    if (!empty($busqueda)) {
        $where = " WHERE u.nombre_usuario LIKE :busqueda 
                   OR p.nombre LIKE :busqueda
                   OR p.numero_identificacion LIKE :busqueda";
        $params[':busqueda'] = "%$busqueda%";
    }

    $sqlCount = "SELECT COUNT(*) as total 
                 FROM usuario u
                 LEFT JOIN personas p ON u.id_persona = p.id_persona
                 $where";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = $stmtCount->fetchColumn();

    $sqlData = "
        SELECT u.id_usuario, u.id_persona, u.nombre_usuario, u.activo, 
               p.nombre AS persona_nombre, p.numero_identificacion
        FROM usuario u
        LEFT JOIN personas p ON u.id_persona = p.id_persona
        $where
        ORDER BY u.nombre_usuario
        LIMIT :limite OFFSET :offset
    ";
    $stmtData = $pdo->prepare($sqlData);
    foreach ($params as $key => $value) {
        $stmtData->bindValue($key, $value);
    }
    $stmtData->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();
    $datos = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    return ['datos' => $datos, 'total' => $total];
}

// ============================================================
// 10. FUNCIONES PARA ROLES Y PERMISOS
// ============================================================

function obtenerRoles() {
    global $pdo;
    $stmt = $pdo->query("SELECT id_rol, nombre_rol FROM rol ORDER BY nombre_rol");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerRolesDeUsuario($id_user) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT DISTINCT id_rol FROM permiso WHERE id_usuario = :id_user");
    $stmt->execute([':id_user' => $id_user]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function obtenerTitularesDeUsuario($id_user) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT DISTINCT id_titular FROM permiso WHERE id_usuario = :id_user");
    $stmt->execute([':id_user' => $id_user]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function obtenerTitularesParaPermisos() {
    global $pdo;
    $stmt = $pdo->query("SELECT id_titular, nombre FROM titular ORDER BY nombre");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function guardarPermisosUsuario($id_user, $roles_ids, $titulares_ids) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("DELETE FROM permiso WHERE id_usuario = :id_user");
        $stmt->execute([':id_user' => $id_user]);
        if (!empty($roles_ids) && !empty($titulares_ids)) {
            $stmt = $pdo->prepare("
                INSERT INTO permiso (id_usuario, id_titular, id_rol)
                VALUES (:id_user, :id_titular, :id_rol)
            ");
            foreach ($roles_ids as $id_rol) {
                foreach ($titulares_ids as $id_titular) {
                    $stmt->execute([
                        ':id_user' => $id_user,
                        ':id_titular' => $id_titular,
                        ':id_rol' => $id_rol
                    ]);
                }
            }
        }
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// ============================================================
// 11. FUNCIONES DE MENSAJES FLASH
// ============================================================

function setMensaje($mensaje, $tipo = 'success') {
    $_SESSION['flash_mensaje'] = $mensaje;
    $_SESSION['flash_tipo'] = $tipo;
}

function mostrarMensaje() {
    if (isset($_SESSION['flash_mensaje']) && !empty($_SESSION['flash_mensaje'])) {
        $tipo = $_SESSION['flash_tipo'] ?? 'success';
        echo '<div class="alert alert-' . $tipo . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($_SESSION['flash_mensaje']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
        unset($_SESSION['flash_mensaje'], $_SESSION['flash_tipo']);
        return true;
    }
    return false;
}

// ============================================================
// 12. FUNCIONES DE UTILIDAD GENERAL
// ============================================================

function formatearFecha($fecha, $formato = 'd/m/Y') {
    if (empty($fecha)) return '-';
    return date($formato, strtotime($fecha));
}

function h($texto) {
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}
// ============================================================
// FUNCIONES PARA TIPOS DE LICENCIA
// ============================================================

/**
 * Obtiene todos los tipos de licencia con datos de la dirección ANRS
 * @return array
 */
function obtenerTiposLicencias() {
    global $pdo;
    $stmt = $pdo->query("
        SELECT tl.*, d.nombre AS direccion_nombre
        FROM tipo_licencia tl
        LEFT JOIN direccion_anrs d ON tl.id_direccion_anrs = d.id_direccion_anrs
        ORDER BY tl.nombre
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene un tipo de licencia por ID
 * @param int $id
 * @return array|null
 */
function obtenerTipoLicencia($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM tipo_licencia WHERE id_tipo_licencia = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Busca tipos de licencia con paginación
 * @param string $busqueda
 * @param int $pagina
 * @param int $limite
 * @return array
 */
function buscarTiposLicencias($busqueda = '', $pagina = 1, $limite = 10) {
    global $pdo;
    $offset = ($pagina - 1) * $limite;
    $params = [];
    $where = "";

    if (!empty($busqueda)) {
        $where = " WHERE tl.nombre LIKE :busqueda 
                   OR d.nombre LIKE :busqueda";
        $params[':busqueda'] = "%$busqueda%";
    }

    // Contar total
    $sqlCount = "SELECT COUNT(*) as total 
                 FROM tipo_licencia tl
                 LEFT JOIN direccion_anrs d ON tl.id_direccion_anrs = d.id_direccion_anrs
                 $where";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = $stmtCount->fetchColumn();

    // Obtener datos
    $sqlData = "
        SELECT tl.*, d.nombre AS direccion_nombre
        FROM tipo_licencia tl
        LEFT JOIN direccion_anrs d ON tl.id_direccion_anrs = d.id_direccion_anrs
        $where
        ORDER BY tl.nombre
        LIMIT :limite OFFSET :offset
    ";

    $stmtData = $pdo->prepare($sqlData);
    foreach ($params as $key => $value) {
        $stmtData->bindValue($key, $value);
    }
    $stmtData->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();
    $datos = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    return ['datos' => $datos, 'total' => $total];
}

/**
 * Verifica si un tipo de licencia tiene dependencias (distribuidores o productos)
 * @param int $id
 * @return array
 */
function verificarDependenciasTipoLicencia($id) {
    global $pdo;
    $dependencias = [];

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM distribuidor WHERE id_tipo_licencia = :id");
    $stmt->execute([':id' => $id]);
    if ($stmt->fetchColumn() > 0) {
        $dependencias[] = 'Distribuidor';
    }

    // También podría usarse en otros módulos si hay relación directa
    // $stmt = $pdo->prepare("SELECT COUNT(*) FROM producto WHERE id_tipo_licencia = :id");
    // ...

    return $dependencias;
}
// ============================================================
// FUNCIONES PARA TIPOS DE MODIFICACIÓN (CATÁLOGO)
// ============================================================

/**
 * Obtiene todos los tipos de modificación
 * @return array
 */
function obtenerTiposModificaciones() {
    global $pdo;
    $stmt = $pdo->query("SELECT id_tipo_modificacion, nombre FROM tipo_modificacion ORDER BY nombre");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene un tipo de modificación por ID
 * @param int $id
 * @return array|null
 */
function obtenerTipoModificacion($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM tipo_modificacion WHERE id_tipo_modificacion = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Busca tipos de modificación con paginación
 * @param string $busqueda
 * @param int $pagina
 * @param int $limite
 * @return array
 */
function buscarTiposModificaciones($busqueda = '', $pagina = 1, $limite = 10) {
    global $pdo;
    $offset = ($pagina - 1) * $limite;
    $params = [];
    $where = "";
    if (!empty($busqueda)) {
        $where = " WHERE nombre LIKE :busqueda";
        $params[':busqueda'] = "%$busqueda%";
    }
    $sqlCount = "SELECT COUNT(*) as total FROM tipo_modificacion $where";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = $stmtCount->fetchColumn();

    $sqlData = "
        SELECT id_tipo_modificacion, nombre
        FROM tipo_modificacion
        $where
        ORDER BY nombre
        LIMIT :limite OFFSET :offset
    ";
    $stmtData = $pdo->prepare($sqlData);
    foreach ($params as $key => $value) {
        $stmtData->bindValue($key, $value);
    }
    $stmtData->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();
    $datos = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    return ['datos' => $datos, 'total' => $total];
}
// ============================================================
// FUNCIONES PARA TIPOS DE DOCUMENTO (CATÁLOGO)
// ============================================================

/**
 * Obtiene todos los tipos de documento
 * @return array
 */
function obtenerTiposDocumentos() {
    global $pdo;
    $stmt = $pdo->query("SELECT id_tipo_documento, nombre FROM tipo_documento ORDER BY nombre");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene un tipo de documento por ID
 * @param int $id
 * @return array|null
 */
function obtenerTipoDocumento($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM tipo_documento WHERE id_tipo_documento = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Busca tipos de documento con paginación
 * @param string $busqueda
 * @param int $pagina
 * @param int $limite
 * @return array
 */
function buscarTiposDocumentos($busqueda = '', $pagina = 1, $limite = 10) {
    global $pdo;
    $offset = ($pagina - 1) * $limite;
    $params = [];
    $where = "";
    if (!empty($busqueda)) {
        $where = " WHERE nombre LIKE :busqueda";
        $params[':busqueda'] = "%$busqueda%";
    }
    $sqlCount = "SELECT COUNT(*) as total FROM tipo_documento $where";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = $stmtCount->fetchColumn();

    $sqlData = "
        SELECT id_tipo_documento, nombre
        FROM tipo_documento
        $where
        ORDER BY nombre
        LIMIT :limite OFFSET :offset
    ";
    $stmtData = $pdo->prepare($sqlData);
    foreach ($params as $key => $value) {
        $stmtData->bindValue($key, $value);
    }
    $stmtData->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();
    $datos = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    return ['datos' => $datos, 'total' => $total];
}
// ============================================================
// FUNCIONES PARA REQUISITOS DE DOCUMENTO (CATÁLOGO)
// ============================================================

/**
 * Obtiene todos los requisitos con sus relaciones (nombres)
 * @return array
 */
function obtenerRequisitos() {
    global $pdo;
    $stmt = $pdo->query("
        SELECT r.*,
               tl.nombre AS tipo_licencia_nombre,
               tt.nombre AS tipo_tramite_nombre,
               tm.nombre AS tipo_modificacion_nombre,
               td.nombre AS tipo_documento_nombre
        FROM requisito_documento r
        LEFT JOIN tipo_licencia tl ON r.id_tipo_licencia = tl.id_tipo_licencia
        LEFT JOIN tipo_tramite tt ON r.id_tipo_tramite = tt.id_tipo_tramite
        LEFT JOIN tipo_modificacion tm ON r.id_tipo_modificacion = tm.id_tipo_modificacion
        LEFT JOIN tipo_documento td ON r.id_tipo_documento = td.id_tipo_documento
        ORDER BY tl.nombre, tt.nombre, tm.nombre, td.nombre
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene un requisito por ID con sus relaciones
 * @param int $id
 * @return array|null
 */
function obtenerRequisito($id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT r.*,
               tl.nombre AS tipo_licencia_nombre,
               tt.nombre AS tipo_tramite_nombre,
               tm.nombre AS tipo_modificacion_nombre,
               td.nombre AS tipo_documento_nombre
        FROM requisito_documento r
        LEFT JOIN tipo_licencia tl ON r.id_tipo_licencia = tl.id_tipo_licencia
        LEFT JOIN tipo_tramite tt ON r.id_tipo_tramite = tt.id_tipo_tramite
        LEFT JOIN tipo_modificacion tm ON r.id_tipo_modificacion = tm.id_tipo_modificacion
        LEFT JOIN tipo_documento td ON r.id_tipo_documento = td.id_tipo_documento
        WHERE r.id_requisito = :id
    ");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Busca requisitos con paginación y filtros
 * @param string $busqueda
 * @param int $pagina
 * @param int $limite
 * @return array
 */
function buscarRequisitos($busqueda = '', $id_direccion_anrs = 0, $pagina = 1, $limite = 10) {
    global $pdo;

    $offset = ($pagina - 1) * $limite;
    $where  = [];
    $params = [];

    // Filtro por dirección ANRS
    if ($id_direccion_anrs > 0) {
        $where[] = "tl.id_direccion_anrs = :id_direccion_anrs";
        $params[':id_direccion_anrs'] = $id_direccion_anrs;
    }

    // Filtro por texto libre
    if ($busqueda !== '') {
        $where[] = "(tl.nombre LIKE :busqueda
                  OR tt.nombre LIKE :busqueda
                  OR tm.nombre LIKE :busqueda
                  OR td.nombre LIKE :busqueda
                  OR da.nombre LIKE :busqueda)";
        $params[':busqueda'] = '%' . $busqueda . '%';
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Total
    $sqlTotal = "SELECT COUNT(*)
                 FROM requisito_documento rd
                 JOIN tipo_licencia      tl ON tl.id_tipo_licencia     = rd.id_tipo_licencia
                 JOIN direccion_anrs     da ON da.id_direccion_anrs    = tl.id_direccion_anrs
                 JOIN tipo_tramite       tt ON tt.id_tipo_tramite      = rd.id_tipo_tramite
                 LEFT JOIN tipo_modificacion tm ON tm.id_tipo_modificacion = rd.id_tipo_modificacion
                 JOIN tipo_documento     td ON td.id_tipo_documento    = rd.id_tipo_documento
                 $whereSql";
    $stmt = $pdo->prepare($sqlTotal);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    // Datos
    $sqlDatos = "SELECT rd.id_requisito,
                        rd.obligatorio,
                        rd.id_tipo_licencia,
                        rd.id_tipo_tramite,
                        rd.id_tipo_modificacion,
                        rd.id_tipo_documento,
                        tl.nombre AS tipo_licencia_nombre,
                        tt.nombre AS tipo_tramite_nombre,
                        tm.nombre AS tipo_modificacion_nombre,
                        td.nombre AS tipo_documento_nombre,
                        da.nombre AS direccion_anrs_nombre
                 FROM requisito_documento rd
                 JOIN tipo_licencia      tl ON tl.id_tipo_licencia     = rd.id_tipo_licencia
                 JOIN direccion_anrs     da ON da.id_direccion_anrs    = tl.id_direccion_anrs
                 JOIN tipo_tramite       tt ON tt.id_tipo_tramite      = rd.id_tipo_tramite
                 LEFT JOIN tipo_modificacion tm ON tm.id_tipo_modificacion = rd.id_tipo_modificacion
                 JOIN tipo_documento     td ON td.id_tipo_documento    = rd.id_tipo_documento
                 $whereSql
                 ORDER BY rd.id_requisito DESC
                 LIMIT :limite OFFSET :offset";

    $stmt = $pdo->prepare($sqlDatos);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'datos' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'total' => $total
    ];
}

/**
 * Verifica si un requisito ya existe (para evitar duplicados)
 * @param int $id_tipo_licencia
 * @param int $id_tipo_tramite
 * @param int|null $id_tipo_modificacion
 * @param int $id_tipo_documento
 * @param int|null $excluir_id  ID a excluir en edición
 * @return bool
 */
function requisitoExiste($id_tipo_licencia, $id_tipo_tramite, $id_tipo_modificacion, $id_tipo_documento, $excluir_id = null) {
    global $pdo;
    $sql = "SELECT COUNT(*) FROM requisito_documento 
            WHERE id_tipo_licencia = :id_tipo_licencia 
              AND id_tipo_tramite = :id_tipo_tramite 
              AND (id_tipo_modificacion = :id_tipo_modificacion OR (id_tipo_modificacion IS NULL AND :id_tipo_modificacion IS NULL))
              AND id_tipo_documento = :id_tipo_documento";
    $params = [
        ':id_tipo_licencia' => $id_tipo_licencia,
        ':id_tipo_tramite' => $id_tipo_tramite,
        ':id_tipo_modificacion' => $id_tipo_modificacion,
        ':id_tipo_documento' => $id_tipo_documento
    ];
    if ($excluir_id) {
        $sql .= " AND id_requisito != :excluir_id";
        $params[':excluir_id'] = $excluir_id;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() > 0;
}
// ============================================================
// FUNCIONES PARA SOLICITUDES (NUEVO MÓDULO)
// ============================================================

/**
 * Obtiene el nombre de un estado por ID
 * @param int $id_estado
 * @return string|null
 */
function obtenerNombreEstado($id_estado) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT nombre FROM estado_solicitud WHERE id_estado = :id");
    $stmt->execute([':id' => $id_estado]);
    return $stmt->fetchColumn();
}

/**
 * Obtiene todos los estados
 * @return array
 */
function obtenerEstados() {
    global $pdo;
    $stmt = $pdo->query("SELECT id_estado, nombre FROM estado_solicitud ORDER BY nombre");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene una solicitud por ID con todos sus datos relacionados
 * @param int $id_solicitud
 * @return array|null
 */
function obtenerSolicitudPorId($id_solicitud) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT s.*, 
               p.nombre AS producto_nombre, 
               p.marca AS producto_marca,
               p.numero_registro AS producto_numero_registro,
               p.fecha_registro_inicial AS producto_fecha_registro_inicial,
               p.fecha_vencimiento_registro AS producto_fecha_vencimiento_registro,
               per.nombre AS representante_nombre,
               t.nombre AS titular_nombre,
               d.nombre AS direccion_nombre,
               tl.nombre AS tipo_licencia_nombre,
               tt.nombre AS tipo_tramite_nombre,
               tm.nombre AS tipo_modificacion_nombre,
               e.nombre AS estado_nombre
        FROM solicitud s
        LEFT JOIN producto p ON s.id_producto = p.id_producto
        LEFT JOIN personas per ON s.id_representante_legal = per.id_persona
        LEFT JOIN titular t ON s.id_titular = t.id_titular
        LEFT JOIN direccion_anrs d ON s.id_direccion_anrs = d.id_direccion_anrs
        LEFT JOIN tipo_licencia tl ON s.id_tipo_licencia = tl.id_tipo_licencia
        LEFT JOIN tipo_tramite tt ON s.id_tipo_tramite = tt.id_tipo_tramite
        LEFT JOIN tipo_modificacion tm ON s.id_tipo_modificacion = tm.id_tipo_modificacion
        LEFT JOIN estado_solicitud e ON s.id_estado_actual = e.id_estado
        WHERE s.id_solicitud = :id AND s.deleted_at IS NULL
    ");
    $stmt->execute([':id' => $id_solicitud]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Obtiene los documentos de una solicitud
 * @param int $id_solicitud
 * @return array
 */
function obtenerDocumentosSolicitud($id_solicitud) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT d.*, td.nombre AS tipo_documento_nombre, u.nombre_usuario AS usuario_nombre
        FROM documento_solicitud d
        LEFT JOIN tipo_documento td ON d.id_tipo_documento = td.id_tipo_documento
        LEFT JOIN usuario u ON d.id_usuario_subio = u.id_usuario
        WHERE d.id_solicitud = :id_solicitud AND d.deleted_at IS NULL
        ORDER BY d.fecha_subida DESC
    ");
    $stmt->execute([':id_solicitud' => $id_solicitud]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene las revisiones de una solicitud
 * @param int $id_solicitud
 * @return array
 */
function obtenerRevisionesSolicitud($id_solicitud) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT r.*, 
               e_ant.nombre AS estado_anterior_nombre,
               e_nue.nombre AS estado_nuevo_nombre,
               u.nombre_usuario AS evaluador_nombre
        FROM solicitud_revision r
        LEFT JOIN estado_solicitud e_ant ON r.id_estado_anterior = e_ant.id_estado
        LEFT JOIN estado_solicitud e_nue ON r.id_estado_nuevo = e_nue.id_estado
        LEFT JOIN usuario u ON r.id_usuario_evaluador = u.id_usuario
        WHERE r.id_solicitud = :id_solicitud
        ORDER BY r.fecha_revision DESC
    ");
    $stmt->execute([':id_solicitud' => $id_solicitud]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene una solicitud con documentos y revisiones (para detalle)
 * @param int $id_solicitud
 * @return array|null
 */
function obtenerSolicitudCompleta($id_solicitud) {
    $solicitud = obtenerSolicitudPorId($id_solicitud);
    if (!$solicitud) return null;
    $solicitud['documentos'] = obtenerDocumentosSolicitud($id_solicitud);
    $solicitud['revisiones'] = obtenerRevisionesSolicitud($id_solicitud);
    return $solicitud;
}

function buscarSolicitudes($filtros = [], $pagina = 1, $limite = 10) {
    global $pdo;
    $offset = ($pagina - 1) * $limite;
    $params = [];
    $where = "WHERE s.deleted_at IS NULL";

    // Filtros básicos
    if (!empty($filtros['estado'])) {
        $where .= " AND s.id_estado_actual = ?";
        $params[] = $filtros['estado'];
    }
    if (!empty($filtros['titular'])) {
        $where .= " AND s.id_titular = ?";
        $params[] = $filtros['titular'];
    }
    if (!empty($filtros['fecha_desde'])) {
        $where .= " AND DATE(s.fecha_solicita) >= ?";
        $params[] = $filtros['fecha_desde'];
    }
    if (!empty($filtros['fecha_hasta'])) {
        $where .= " AND DATE(s.fecha_solicita) <= ?";
        $params[] = $filtros['fecha_hasta'];
    }
    // Búsqueda ampliada: producto, código, tipo de trámite, tipo de modificación
    if (!empty($filtros['busqueda'])) {
        $where .= " AND (
            p.nombre LIKE ? 
            OR s.codigo_control_empresa LIKE ? 
            OR tt.nombre LIKE ?
            OR tm.nombre LIKE ?
        )";
        $busqueda_like = "%" . $filtros['busqueda'] . "%";
        $params[] = $busqueda_like;
        $params[] = $busqueda_like;
        $params[] = $busqueda_like;
        $params[] = $busqueda_like;
    }

    // Restricción por titular (si no es admin)
    if (!esAdmin()) {
        $titulares = $_SESSION['titulares'] ?? [];
        if (empty($titulares)) {
            return ['datos' => [], 'total' => 0];
        }
        $placeholders = implode(',', array_fill(0, count($titulares), '?'));
        $where .= " AND s.id_titular IN ($placeholders)";
        foreach ($titulares as $t) {
            $params[] = $t;
        }
    }

    // --- Conteo total (con los mismos JOINs que la consulta de datos) ---
    $sqlCount = "SELECT COUNT(*) as total 
                 FROM solicitud s 
                 LEFT JOIN producto p ON s.id_producto = p.id_producto
                 LEFT JOIN tipo_tramite tt ON s.id_tipo_tramite = tt.id_tipo_tramite
                 LEFT JOIN tipo_modificacion tm ON s.id_tipo_modificacion = tm.id_tipo_modificacion
                 $where";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = $stmtCount->fetchColumn();

    // --- Datos paginados ---
    $sqlData = "
        SELECT s.*, 
               p.nombre AS producto_nombre,
               t.nombre AS titular_nombre,
               e.nombre AS estado_nombre,
               tt.nombre AS tipo_tramite_nombre,
               tm.nombre AS tipo_modificacion_nombre
        FROM solicitud s
        LEFT JOIN producto p ON s.id_producto = p.id_producto
        LEFT JOIN titular t ON s.id_titular = t.id_titular
        LEFT JOIN estado_solicitud e ON s.id_estado_actual = e.id_estado
        LEFT JOIN tipo_tramite tt ON s.id_tipo_tramite = tt.id_tipo_tramite
        LEFT JOIN tipo_modificacion tm ON s.id_tipo_modificacion = tm.id_tipo_modificacion
        $where
        ORDER BY s.fecha_solicita DESC
        LIMIT ? OFFSET ?
    ";

    $stmtData = $pdo->prepare($sqlData);

    $idx = 1;
    foreach ($params as $value) {
        $stmtData->bindValue($idx, $value);
        $idx++;
    }
    $stmtData->bindValue($idx, (int)$limite, PDO::PARAM_INT);
    $stmtData->bindValue($idx + 1, (int)$offset, PDO::PARAM_INT);

    $stmtData->execute();
    $datos = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    // Resolver producto propuesto si es trámite nuevo
    foreach ($datos as &$row) {
        if (empty($row['id_producto']) && !empty($row['producto_datos_propuestos'])) {
            $propuesto = json_decode($row['producto_datos_propuestos'], true);
            if (is_array($propuesto) && !empty($propuesto['nombre'])) {
                $row['producto_nombre'] = $propuesto['nombre'];
            }
        }
        if (empty($row['producto_nombre'])) {
            $row['producto_nombre'] = '(Producto no asignado)';
        }
    }
    unset($row);

    return ['datos' => $datos, 'total' => $total];
}

/**
 * Obtiene los titulares a los que el usuario actual tiene acceso.
 * - Si es admin: devuelve todos los titulares.
 * - Si no: solo los que están en $_SESSION['titulares'].
 * @return array
 */
function obtenerTitularesAccesibles() {
    global $pdo;
    
    if (esAdmin()) {
        $stmt = $pdo->query("SELECT id_titular, nombre FROM titular ORDER BY nombre");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $ids = $_SESSION['titulares'] ?? [];
    if (empty($ids)) {
        return [];
    }
    
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id_titular, nombre FROM titular WHERE id_titular IN ($placeholders) ORDER BY nombre");
    $stmt->execute($ids);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================================
// VALIDACIONES Y PERMISOS
// ============================================================

/**
 * Verifica si el usuario puede ver/editar una solicitud
 * @param array $solicitud
 * @param string $accion  'view', 'edit', 'action'
 * @return bool
 */
function puedeAccederSolicitud($solicitud, $accion = 'view') {
    if (esAdmin()) return true;
    
    if (tieneRol(4)) {
        return $accion === 'view';
    }
    
    if (!tieneAccesoATitular($solicitud['id_titular'])) {
        return false;
    }
    
    if (tieneRol(1)) {
        if ($solicitud['id_representante_legal'] != $_SESSION['id_persona']) {
            return false;
        }
        if ($accion === 'edit' && $solicitud['id_estado_actual'] != obtenerIdEstado('Nueva')) {
            return false;
        }
        return true;
    }
    
    if (tieneRol(2)) {
        return true;
    }
    
    return false;
}

/**
 * Obtiene el ID de un estado por su nombre
 * @param string $nombre
 * @return int|null
 */
function obtenerIdEstado($nombre) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id_estado FROM estado_solicitud WHERE nombre = :nombre");
    $stmt->execute([':nombre' => $nombre]);
    return $stmt->fetchColumn();
}

/**
 * Verifica si una transición de estado es válida
 * @param int $id_estado_actual
 * @param string $accion
 * @return bool
 */
function transicionValida($id_estado_actual, $accion) {
    $estados = [
        'enviar' => ['Nueva'],
        'tomar_evaluacion' => ['Solicitud_ingresado'],
        'aprobar' => ['Solicitud_evaluacion'],
        'rechazar' => ['Solicitud_evaluacion'],
        'reabrir' => ['Solicitud_rechazada'],
        'cancelar' => ['Nueva', 'Solicitud_ingresado', 'Solicitud_evaluacion', 'Solicitud_rechazada']
    ];
    if (!isset($estados[$accion])) return false;
    $nombres = $estados[$accion];
    $estado_actual_nombre = obtenerNombreEstado($id_estado_actual);
    return in_array($estado_actual_nombre, $nombres);
}

/**
 * Verifica si un número de revisión es único para una solicitud
 * @param int $id_solicitud
 * @param int $numero_revision
 * @param int|null $excluir_id
 * @return bool
 */
function revisionUnica($id_solicitud, $numero_revision, $excluir_id = null) {
    global $pdo;
    $sql = "SELECT COUNT(*) FROM solicitud_revision WHERE id_solicitud = :id_solicitud AND numero_revision = :numero";
    $params = [':id_solicitud' => $id_solicitud, ':numero' => $numero_revision];
    if ($excluir_id) {
        $sql .= " AND id_revision != :excluir";
        $params[':excluir'] = $excluir_id;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() == 0;
}

// ============================================================
// FUNCIONES PARA CAMBIOS DE ESTADO Y REVISIONES
// ============================================================

/**
 * Cambia el estado de una solicitud con todas las validaciones
 * @param int $id_solicitud
 * @param string $accion
 * @param int $id_usuario
 * @param array $datos
 * @return array
 */
function cambiarEstadoSolicitud($id_solicitud, $accion, $id_usuario, $datos = []) {
    global $pdo;
    $solicitud = obtenerSolicitudPorId($id_solicitud);
    if (!$solicitud) {
        return ['success' => false, 'message' => 'Solicitud no encontrada.'];
    }

    $estado_nombre = $solicitud['estado_nombre'];
    if (in_array($estado_nombre, ['Solicitud_aprobado', 'Solicitud_cancelado'])) {
        return ['success' => false, 'message' => 'No se puede modificar una solicitud en estado terminal.'];
    }

    if (!transicionValida($solicitud['id_estado_actual'], $accion)) {
        return ['success' => false, 'message' => 'Transición no permitida desde el estado actual.'];
    }

    $destinos = [
        'enviar' => 'Solicitud_ingresado',
        'tomar_evaluacion' => 'Solicitud_evaluacion',
        'aprobar' => 'Solicitud_aprobado',
        'rechazar' => 'Solicitud_rechazada',
        'reabrir' => 'Solicitud_evaluacion',
        'cancelar' => 'Solicitud_cancelado'
    ];
    $destino_nombre = $destinos[$accion];
    $id_destino = obtenerIdEstado($destino_nombre);
    if (!$id_destino) {
        return ['success' => false, 'message' => 'Estado destino no encontrado.'];
    }

    if ($accion === 'enviar') {
        if (empty($solicitud['codigo_control_empresa'])) {
            return ['success' => false, 'message' => 'El código de control de empresa es obligatorio.'];
        }
    }

    if ($accion === 'rechazar' || $accion === 'reabrir') {
        if (empty($datos['comentario_evaluador'])) {
            return ['success' => false, 'message' => 'El comentario es obligatorio para esta acción.'];
        }
        if (empty($datos['numero_revision'])) {
            return ['success' => false, 'message' => 'El número de revisión es obligatorio.'];
        }
        if (!revisionUnica($id_solicitud, $datos['numero_revision'])) {
            return ['success' => false, 'message' => 'El número de revisión ya existe para esta solicitud.'];
        }
    }

    if ($accion === 'cancelar') {
        if (!empty($datos['numero_revision']) && !revisionUnica($id_solicitud, $datos['numero_revision'])) {
            return ['success' => false, 'message' => 'El número de revisión ya existe para esta solicitud.'];
        }
    }

    try {
        $pdo->beginTransaction();

        $id_estado_anterior = $solicitud['id_estado_actual'];

        $stmt = $pdo->prepare("UPDATE solicitud SET id_estado_actual = :id_estado WHERE id_solicitud = :id");
        $stmt->execute([':id_estado' => $id_destino, ':id' => $id_solicitud]);

        if ($accion === 'aprobar') {
            $producto_actualizado = false;
            if (!empty($solicitud['producto_nombre_nuevo'])) {
                $stmtProd = $pdo->prepare("UPDATE producto SET nombre = :nombre WHERE id_producto = :id");
                $stmtProd->execute([':nombre' => $solicitud['producto_nombre_nuevo'], ':id' => $solicitud['id_producto']]);
                $producto_actualizado = true;
            }
            if (!empty($solicitud['producto_marca_nueva'])) {
                $stmtProd = $pdo->prepare("UPDATE producto SET marca = :marca WHERE id_producto = :id");
                $stmtProd->execute([':marca' => $solicitud['producto_marca_nueva'], ':id' => $solicitud['id_producto']]);
                $producto_actualizado = true;
            }
            if ($producto_actualizado) {
                $stmtClean = $pdo->prepare("UPDATE solicitud SET producto_nombre_nuevo = NULL, producto_marca_nueva = NULL WHERE id_solicitud = :id");
                $stmtClean->execute([':id' => $id_solicitud]);
            }
        }

        $registrar_revision = in_array($accion, ['rechazar', 'reabrir']) || ($accion === 'cancelar' && !empty($datos['numero_revision']));
        if ($registrar_revision) {
            $ultimo_numero = 0;
            if (empty($datos['numero_revision'])) {
                $stmtNum = $pdo->prepare("SELECT MAX(numero_revision) FROM solicitud_revision WHERE id_solicitud = :id");
                $stmtNum->execute([':id' => $id_solicitud]);
                $ultimo_numero = (int)$stmtNum->fetchColumn();
                $datos['numero_revision'] = $ultimo_numero + 1;
            }

            $stmtRev = $pdo->prepare("
                INSERT INTO solicitud_revision 
                (id_solicitud, numero_revision, id_usuario_evaluador, fecha_revision, 
                 id_estado_anterior, id_estado_nuevo, comentario_evaluador, comentario_solicitante)
                VALUES 
                (:id_solicitud, :numero_revision, :id_usuario, NOW(), 
                 :id_estado_anterior, :id_estado_nuevo, :comentario, NULL)
            ");
            $stmtRev->execute([
                ':id_solicitud' => $id_solicitud,
                ':numero_revision' => $datos['numero_revision'],
                ':id_usuario' => $id_usuario,
                ':id_estado_anterior' => $id_estado_anterior,
                ':id_estado_nuevo' => $id_destino,
                ':comentario' => $datos['comentario_evaluador'] ?? null
            ]);
        }

        $pdo->commit();
        return ['success' => true, 'message' => 'Estado actualizado correctamente.', 'data' => ['nuevo_estado' => $destino_nombre]];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()];
    }
}

// ============================================================
// FUNCIONES PARA DOCUMENTOS
// ============================================================

/**
 * Sube un documento asociado a una solicitud (y opcionalmente a una revisión)
 * @param int $id_solicitud
 * @param int $id_tipo_documento
 * @param string $ruta_archivo
 * @param int $id_usuario
 * @param int|null $id_revision
 * @return bool
 */
function subirDocumentoSolicitud($id_solicitud, $id_tipo_documento, $ruta_archivo, $id_usuario, $id_revision = null) {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO documento_solicitud (id_solicitud, id_revision, id_tipo_documento, ruta_archivo, fecha_subida, id_usuario_subio)
        VALUES (:id_solicitud, :id_revision, :id_tipo_documento, :ruta, NOW(), :id_usuario)
    ");
    return $stmt->execute([
        ':id_solicitud' => $id_solicitud,
        ':id_revision' => $id_revision,
        ':id_tipo_documento' => $id_tipo_documento,
        ':ruta' => $ruta_archivo,
        ':id_usuario' => $id_usuario
    ]);
}

/**
 * Elimina lógicamente un documento (soft delete)
 * @param int $id_documento
 * @return bool
 */
function eliminarDocumentoSolicitud($id_documento) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE documento_solicitud SET deleted_at = NOW() WHERE id_documento = :id");
    return $stmt->execute([':id' => $id_documento]);
}

// ============================================================
// FUNCIONES PARA VALIDACIÓN DE ACCIONES SEGÚN ROL Y ESTADO
// ============================================================

/**
 * Obtiene las acciones permitidas para una solicitud según el rol y estado
 * @param array $solicitud
 * @return array
 */
function obtenerAccionesPermitidas($solicitud) {
    $acciones = [];
    $estado = $solicitud['estado_nombre'];
    $es_admin = esAdmin();
    $rol = $_SESSION['roles'] ?? [];
    $es_evaluador = in_array(2, $rol) || $es_admin;

    // Solo mostrar Aprobar si es evaluador/admin y estado = Solicitud_evaluacion
    if (($es_evaluador || $es_admin) && $estado === 'Solicitud_evaluacion') {
        $acciones[] = ['accion' => 'aprobar', 'label' => 'Aprobar', 'icon' => 'fa-check', 'class' => 'btn-success'];
    }
    // Las demás acciones se eliminan
    return $acciones;
}
/**
 * Verifica si se puede subir documentos en el estado actual
 * @param string $estado_nombre
 * @return bool
 */
function puedeSubirDocumentos($estado_nombre) {
    return in_array($estado_nombre, ['Nueva', 'Solicitud_ingresado', 'Solicitud_evaluacion', 'Solicitud_rechazada']);
}

// ============================================================
// FUNCIONES PARA REQUISITOS Y FILTROS DINÁMICOS (NUEVAS)
// ============================================================

/**
 * Obtiene los documentos requeridos según la combinación de catálogos
 * @param int $id_tipo_licencia
 * @param int $id_tipo_tramite
 * @param int|null $id_tipo_modificacion
 * @return array
 */
function obtenerRequisitosPorCombinacion($id_tipo_licencia, $id_tipo_tramite, $id_tipo_modificacion = null) {
    global $pdo;
    $sql = "
        SELECT r.*, td.nombre AS tipo_documento_nombre
        FROM requisito_documento r
        LEFT JOIN tipo_documento td ON r.id_tipo_documento = td.id_tipo_documento
        WHERE r.id_tipo_licencia = :id_tipo_licencia 
          AND r.id_tipo_tramite = :id_tipo_tramite
          AND (r.id_tipo_modificacion = :id_tipo_modificacion OR (r.id_tipo_modificacion IS NULL AND :id_tipo_modificacion IS NULL))
        ORDER BY td.nombre
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_tipo_licencia' => $id_tipo_licencia,
        ':id_tipo_tramite' => $id_tipo_tramite,
        ':id_tipo_modificacion' => $id_tipo_modificacion
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene los requisitos de documento aplicables a una solicitud,
 * según su tipo de licencia, tipo de trámite y tipo de modificación.
 * 
 * @param array $solicitud  Array con id_tipo_licencia, id_tipo_tramite, id_tipo_modificacion
 * @return array  Lista de requisitos con datos del tipo de documento
 */
function obtenerRequisitosDeSolicitud($solicitud) {
    global $pdo;

    $id_tipo_licencia     = $solicitud['id_tipo_licencia'] ?? null;
    $id_tipo_tramite      = $solicitud['id_tipo_tramite'] ?? null;
    $id_tipo_modificacion = $solicitud['id_tipo_modificacion'] ?? null;

    if (!$id_tipo_licencia || !$id_tipo_tramite) {
        return [];
    }

    $sql = "
        SELECT rd.id_requisito,
               rd.obligatorio,
               rd.id_tipo_documento,
               td.nombre AS tipo_documento_nombre
        FROM requisito_documento rd
        JOIN tipo_documento td ON td.id_tipo_documento = rd.id_tipo_documento
        WHERE rd.id_tipo_licencia = :id_tipo_licencia
          AND rd.id_tipo_tramite  = :id_tipo_tramite
          AND (
                rd.id_tipo_modificacion = :id_tipo_modificacion
                OR (rd.id_tipo_modificacion IS NULL AND :id_tipo_modificacion_null IS NULL)
              )
        ORDER BY rd.obligatorio DESC, td.nombre
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_tipo_licencia'          => $id_tipo_licencia,
        ':id_tipo_tramite'           => $id_tipo_tramite,
        ':id_tipo_modificacion'      => $id_tipo_modificacion,
        ':id_tipo_modificacion_null' => $id_tipo_modificacion
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Busca productos por número de registro
 * @param string $numero_registro
 * @return array
 */
function buscarProductosPorRegistro($numero_registro) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT id_producto, numero_registro, nombre, marca 
        FROM producto 
        WHERE numero_registro LIKE :busqueda AND deleted_at IS NULL
        ORDER BY nombre
        LIMIT 10
    ");
    $stmt->execute([':busqueda' => "%$numero_registro%"]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
// ============================================================
// FUNCIONES PARA MANEJO DE PRODUCTO PROPUESTO (JSON)
// ============================================================

/**
 * Guarda los datos propuestos del producto en la solicitud
 * @param int $id_solicitud
 * @param array $datos  Datos del producto (nombre, marca, id_fabricante, etc.)
 * @return bool
 */
function guardarProductoPropuesto($id_solicitud, $datos) {
    global $pdo;
    // Filtrar solo los campos que existen en la tabla producto
    $campos_permitidos = ['nombre', 'marca', 'id_fabricante', 'numero_registro', 
                          'fecha_registro_inicial', 'fecha_vencimiento_registro', 'estado_producto'];
    $datos_filtrados = array_intersect_key($datos, array_flip($campos_permitidos));
    // Eliminar campos vacíos (opcional)
    $datos_filtrados = array_filter($datos_filtrados, function($v) { return $v !== '' && $v !== null; });
    if (empty($datos_filtrados)) {
        $datos_filtrados = null;
    }
    $json = json_encode($datos_filtrados);
    $stmt = $pdo->prepare("UPDATE solicitud SET producto_datos_propuestos = :json WHERE id_solicitud = :id");
    return $stmt->execute([':json' => $json, ':id' => $id_solicitud]);
}

/**
 * Obtiene los datos propuestos del producto de una solicitud
 * @param int $id_solicitud
 * @return array|null
 */
function obtenerProductoPropuesto($id_solicitud) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT producto_datos_propuestos FROM solicitud WHERE id_solicitud = :id");
    $stmt->execute([':id' => $id_solicitud]);
    $json = $stmt->fetchColumn();
    if ($json) {
        return json_decode($json, true);
    }
    return null;
}
/**
 * Obtiene los distribuidores asociados a una solicitud (N:N).
 * @param int $id_solicitud
 * @return array
 */
function obtenerDistribuidoresDeSolicitud($id_solicitud) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT d.*, 
               dep.nombre AS departamento_nombre,
               mun.nombre AS municipio_nombre,
               tl.nombre  AS tipo_licencia_nombre
        FROM solicitud_distribuidor sd
        JOIN distribuidor d ON d.id_distribuidor = sd.id_distribuidor
        LEFT JOIN departamento   dep ON d.id_departamento  = dep.id_departamento
        LEFT JOIN municipio      mun ON d.id_municipio     = mun.id_municipio
        LEFT JOIN tipo_licencia  tl  ON d.id_tipo_licencia = tl.id_tipo_licencia
        WHERE sd.id_solicitud = :id
        ORDER BY d.nombre
    ");
    $stmt->execute([':id' => $id_solicitud]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Guarda la lista de distribuidores asociados a una solicitud (reemplaza los existentes).
 * @param int $id_solicitud
 * @param array $ids_distribuidores
 * @return bool
 */
function guardarDistribuidoresDeSolicitud($id_solicitud, $ids_distribuidores) {
    global $pdo;
    try {
        // Borrar los actuales
        $stmt = $pdo->prepare("DELETE FROM solicitud_distribuidor WHERE id_solicitud = :id");
        $stmt->execute([':id' => $id_solicitud]);

        if (empty($ids_distribuidores)) {
            return true;
        }

        // Insertar los nuevos
        $stmt = $pdo->prepare("
            INSERT INTO solicitud_distribuidor (id_solicitud, id_distribuidor) 
            VALUES (:id_solicitud, :id_distribuidor)
        ");
        foreach ($ids_distribuidores as $id_dist) {
            $id_dist = (int)$id_dist;
            if ($id_dist > 0) {
                $stmt->execute([
                    ':id_solicitud'    => $id_solicitud,
                    ':id_distribuidor' => $id_dist
                ]);
            }
        }
        return true;
    } catch (Exception $e) {
        return false;
    }
}
/**
 * Aplica los cambios propuestos del producto al aprobar la solicitud.
 * 
 * IMPORTANTE: Detecta si ya existe una transacción activa (llamada desde otra función)
 * y en ese caso NO inicia una nueva ni hace commit/rollback, dejando que el caller
 * maneje la transacción.
 * 
 * @param int $id_solicitud
 * @param int $id_producto_existente  Si es 0, se crea un nuevo producto; si >0, se actualiza
 * @return array  ['success' => bool, 'message' => string, 'id_producto' => int]
 */
function aplicarProductoPropuesto($id_solicitud, $id_producto_existente = 0) {
    global $pdo;

    $datos = obtenerProductoPropuesto($id_solicitud);
    if (!$datos) {
        return ['success' => false, 'message' => 'No hay datos propuestos para aplicar.'];
    }

    // Validar que los campos obligatorios estén presentes
    $campos_requeridos = ['nombre', 'marca', 'id_fabricante'];
    foreach ($campos_requeridos as $campo) {
        if (empty($datos[$campo])) {
            return ['success' => false, 'message' => "El campo $campo es obligatorio."];
        }
    }

    // ✅ Detectar si ya hay una transacción abierta por el caller
    $transaccion_propia = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $transaccion_propia = true;
    }

    try {
        if ($id_producto_existente > 0) {
            // === ACTUALIZAR PRODUCTO EXISTENTE ===
            $sql = "UPDATE producto SET ";
            $params = [];
            $set_parts = [];
            foreach ($datos as $key => $value) {
                if ($key !== 'id_producto' && $key !== 'deleted_at') {
                    $set_parts[] = "$key = :$key";
                    $params[":$key"] = $value;
                }
            }
            $sql .= implode(', ', $set_parts);
            $sql .= " WHERE id_producto = :id_producto";
            $params[':id_producto'] = $id_producto_existente;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $id_producto = $id_producto_existente;
            $mensaje = 'Producto actualizado correctamente.';
        } else {
            // === CREAR NUEVO PRODUCTO ===
            if (!empty($datos['numero_registro'])) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM producto WHERE numero_registro = :reg AND deleted_at IS NULL");
                $stmt->execute([':reg' => $datos['numero_registro']]);
                if ($stmt->fetchColumn() > 0) {
                    if ($transaccion_propia) {
                        $pdo->rollBack();
                    }
                    return ['success' => false, 'message' => 'El número de registro ya existe en otro producto.'];
                }
            }

            $sql = "INSERT INTO producto (";
            $campos = [];
            $valores = [];
            foreach ($datos as $key => $value) {
                $campos[] = $key;
                $valores[] = ":$key";
            }
            $sql .= implode(', ', $campos) . ") VALUES (" . implode(', ', $valores) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($datos);
            $id_producto = $pdo->lastInsertId();
            $mensaje = 'Producto creado correctamente.';
        }

        // Limpiar los datos propuestos después de aplicarlos
        $stmtClean = $pdo->prepare("UPDATE solicitud SET producto_datos_propuestos = NULL WHERE id_solicitud = :id");
        $stmtClean->execute([':id' => $id_solicitud]);

        // ✅ Solo hacer commit si esta función inició la transacción
        if ($transaccion_propia) {
            $pdo->commit();
        }

        return ['success' => true, 'message' => $mensaje, 'id_producto' => $id_producto];
    } catch (Exception $e) {
        // ✅ Solo hacer rollback si esta función inició la transacción
        if ($transaccion_propia) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Error al aplicar producto: ' . $e->getMessage()];
    }
}
// ============================================================
// FUNCIONES PARA REVISIÓN MANUAL (AGREGAR REVISIÓN)
// ============================================================

/**
 * Obtiene los estados permitidos para una revisión manual según el estado actual
 * @param int $id_estado_actual
 * @return array  Array de IDs de estados permitidos
 */
function obtenerEstadosPermitidosParaRevision($id_estado_actual) {
    global $pdo;
    
    // Obtener el nombre del estado actual
    $estado_actual_nombre = obtenerNombreEstado($id_estado_actual);
    
    // Mapa de transiciones permitidas para revisión manual
    $mapa_estados = [
        'Nueva' => ['Solicitud_ingresado', 'Solicitud_cancelado'],
        'Solicitud_ingresado' => ['Solicitud_evaluacion', 'Solicitud_cancelado'],
        'Solicitud_evaluacion' => ['Solicitud_aprobado', 'Solicitud_rechazada', 'Solicitud_cancelado'],
        'Solicitud_rechazada' => ['Solicitud_evaluacion', 'Solicitud_cancelado']
    ];
    
    // Si el estado actual no está en el mapa, devolver vacío
    if (!isset($mapa_estados[$estado_actual_nombre])) {
        return [];
    }
    
    // Obtener los nombres de los estados destino
    $nombres_destino = $mapa_estados[$estado_actual_nombre];
    $placeholders = implode(',', array_fill(0, count($nombres_destino), '?'));
    $stmt = $pdo->prepare("SELECT id_estado, nombre FROM estado_solicitud WHERE nombre IN ($placeholders)");
    $stmt->execute($nombres_destino);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Agrega una revisión manual a la solicitud (cambia estado y registra revisión)
 * @param int $id_solicitud
 * @param int $id_estado_nuevo
 * @param int $id_usuario
 * @param string $comentario
 * @return array ['success' => bool, 'message' => string]
 */
/**
 * Agrega una revisión manual a la solicitud (cambia estado y registra revisión).
 * 
 * Permisos:
 * - Admin (3) y Evaluador (2): pueden agregar revisiones en estados no terminales.
 * - Solicitante (1): SOLO puede enviar (Nueva -> Solicitud_ingresado).
 * 
 * @param int $id_solicitud
 * @param int $id_estado_nuevo
 * @param int $id_usuario
 * @param string $comentario
 * @return array ['success' => bool, 'message' => string]
 */
function agregarRevisionSolicitud($id_solicitud, $id_estado_nuevo, $id_usuario, $comentario) {
    global $pdo;
    $solicitud = obtenerSolicitudPorId($id_solicitud);
    if (!$solicitud) {
        return ['success' => false, 'message' => 'Solicitud no encontrada.'];
    }

    $es_evaluador_o_admin = esAdmin() || tieneRol(2);
    $es_solicitante       = tieneRol(1);

    // Validación de permisos
    if (!$es_evaluador_o_admin && !$es_solicitante) {
        return ['success' => false, 'message' => 'No tienes permiso para agregar revisiones.'];
    }

    $id_estado_actual     = $solicitud['id_estado_actual'];
    $estado_actual_nombre = $solicitud['estado_nombre'];

    // Estados terminales: nadie puede modificar
    if (in_array($estado_actual_nombre, ['Solicitud_aprobado', 'Solicitud_cancelado'])) {
        return ['success' => false, 'message' => 'No se puede agregar revisión en estado terminal.'];
    }

    // Reglas específicas del solicitante
    if ($es_solicitante && !$es_evaluador_o_admin) {
        if ($estado_actual_nombre !== 'Nueva') {
            return ['success' => false, 'message' => 'Solo puedes modificar la solicitud cuando está en estado Nueva.'];
        }
        $destino_nombre = obtenerNombreEstado($id_estado_nuevo);
        if (!in_array($destino_nombre, ['Solicitud_ingresado', 'Solicitud_cancelado'])) {
            return ['success' => false, 'message' => 'Como solicitante solo puedes enviar (Ingresado) o cancelar (Cancelado) la solicitud.'];
        }
    }

    // Validar transición según el mapa general
    $estados_permitidos = obtenerEstadosPermitidosParaRevision($id_estado_actual);
    $estados_permitidos_ids = array_column($estados_permitidos, 'id_estado');
    if (!in_array($id_estado_nuevo, $estados_permitidos_ids)) {
        return ['success' => false, 'message' => 'El estado destino no está permitido desde el estado actual.'];
    }

    // Calcular número de revisión (autoincremental por solicitud)
    $stmtNum = $pdo->prepare("SELECT MAX(numero_revision) FROM solicitud_revision WHERE id_solicitud = :id");
    $stmtNum->execute([':id' => $id_solicitud]);
    $ultimo_numero = (int)$stmtNum->fetchColumn();
    $numero_revision = $ultimo_numero + 1;

    try {
        $pdo->beginTransaction();

        // 1. Actualizar estado
        $stmt = $pdo->prepare("UPDATE solicitud SET id_estado_actual = :id_estado WHERE id_solicitud = :id");
        $stmt->execute([':id_estado' => $id_estado_nuevo, ':id' => $id_solicitud]);

        // 2. Registrar revisión
        $stmtRev = $pdo->prepare("
            INSERT INTO solicitud_revision 
            (id_solicitud, numero_revision, id_usuario_evaluador, fecha_revision, 
             id_estado_anterior, id_estado_nuevo, comentario_evaluador)
            VALUES 
            (:id_solicitud, :numero_revision, :id_usuario, NOW(), 
             :id_estado_anterior, :id_estado_nuevo, :comentario)
        ");
        $stmtRev->execute([
            ':id_solicitud'        => $id_solicitud,
            ':numero_revision'     => $numero_revision,
            ':id_usuario'          => $id_usuario,
            ':id_estado_anterior'  => $id_estado_actual,
            ':id_estado_nuevo'     => $id_estado_nuevo,
            ':comentario'          => $comentario
        ]);

        // Si el nuevo estado es Aprobado, aplicar cambios de producto
        if (obtenerNombreEstado($id_estado_nuevo) === 'Solicitud_aprobado') {
            $resultado = aplicarProductoPropuesto($id_solicitud, $solicitud['id_producto']);
            if (!$resultado['success']) {
                throw new Exception($resultado['message']);
            }
            if ($resultado['id_producto'] && !$solicitud['id_producto']) {
                $stmtUpdate = $pdo->prepare("UPDATE solicitud SET id_producto = :id_prod WHERE id_solicitud = :id");
                $stmtUpdate->execute([':id_prod' => $resultado['id_producto'], ':id' => $id_solicitud]);
            }
        }

        $pdo->commit();
        return ['success' => true, 'message' => "Revisión #$numero_revision registrada correctamente."];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error al agregar revisión: ' . $e->getMessage()];
    }
}
// ============================================================
// FUNCIONES PARA DASHBOARD (RESUMEN DE SOLICITUDES)
// ============================================================
/**
 * Obtiene la cantidad de solicitudes agrupadas por mes Y tipo de trámite.
 * @return array  Lista con: mes, id_tipo_tramite, tipo_tramite_nombre, total
 */
function obtenerSolicitudesPorMesYTipoTramite($fecha_desde = null, $fecha_hasta = null) {
    global $pdo;

    $filtros = ["s.deleted_at IS NULL"];
    $params = [];

    if (!esAdmin()) {
        $titulares = $_SESSION['titulares'] ?? [];
        if (empty($titulares)) return [];
        $placeholders = implode(',', array_fill(0, count($titulares), '?'));
        $filtros[] = "s.id_titular IN ($placeholders)";
        foreach ($titulares as $t) $params[] = $t;
    }
    if (!empty($fecha_desde)) {
        $filtros[] = "DATE(s.fecha_solicita) >= ?";
        $params[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $filtros[] = "DATE(s.fecha_solicita) <= ?";
        $params[] = $fecha_hasta;
    }

    $where = "WHERE " . implode(' AND ', $filtros);

    $sql = "SELECT DATE_FORMAT(s.fecha_solicita, '%Y-%m') AS mes,
                   tt.id_tipo_tramite,
                   tt.nombre AS tipo_tramite_nombre,
                   COUNT(*) AS total
            FROM solicitud s
            JOIN tipo_tramite tt ON s.id_tipo_tramite = tt.id_tipo_tramite
            $where
            GROUP BY mes, tt.id_tipo_tramite, tt.nombre
            ORDER BY mes ASC, tt.id_tipo_tramite ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene la cantidad de solicitudes agrupadas por mes Y tipo de modificación.
 * @return array  Lista con: mes, id_tipo_modificacion, tipo_modificacion_nombre, total
 */
function obtenerSolicitudesPorMesYTipoModificacion($fecha_desde = null, $fecha_hasta = null) {
    global $pdo;

    $filtros = ["s.deleted_at IS NULL", "s.id_tipo_modificacion IS NOT NULL"];
    $params = [];

    if (!esAdmin()) {
        $titulares = $_SESSION['titulares'] ?? [];
        if (empty($titulares)) return [];
        $placeholders = implode(',', array_fill(0, count($titulares), '?'));
        $filtros[] = "s.id_titular IN ($placeholders)";
        foreach ($titulares as $t) $params[] = $t;
    }
    if (!empty($fecha_desde)) {
        $filtros[] = "DATE(s.fecha_solicita) >= ?";
        $params[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $filtros[] = "DATE(s.fecha_solicita) <= ?";
        $params[] = $fecha_hasta;
    }

    $where = "WHERE " . implode(' AND ', $filtros);

    $sql = "SELECT DATE_FORMAT(s.fecha_solicita, '%Y-%m') AS mes,
                   tm.id_tipo_modificacion,
                   tm.nombre AS tipo_modificacion_nombre,
                   COUNT(*) AS total
            FROM solicitud s
            JOIN tipo_modificacion tm ON s.id_tipo_modificacion = tm.id_tipo_modificacion
            $where
            GROUP BY mes, tm.id_tipo_modificacion, tm.nombre
            ORDER BY mes ASC, tm.id_tipo_modificacion ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Helper: transforma los resultados "mes,tipo,total" en estructura lista para Chart.js
 * con múltiples datasets (una serie por cada tipo).
 *
 * @param array $datos      Resultado de obtenerSolicitudesPorMesYTipo*
 * @param string $campo_id  'id_tipo_tramite' o 'id_tipo_modificacion'
 * @param string $campo_nom 'tipo_tramite_nombre' o 'tipo_modificacion_nombre'
 * @return array ['meses' => [...], 'datasets' => [...]]
 */
function construirSeriesPorMesYTipo($datos, $campo_id, $campo_nom) {
    // 1. Obtener todos los meses únicos ordenados
    $meses = array_values(array_unique(array_column($datos, 'mes')));
    sort($meses);

    // 2. Agrupar por tipo
    $tipos = [];  // id => nombre
    $matriz = []; // id => [mes => total]
    foreach ($datos as $d) {
        $id  = $d[$campo_id];
        $nom = $d[$campo_nom];
        $tipos[$id] = $nom;
        $matriz[$id][$d['mes']] = (int)$d['total'];
    }

    // 3. Paleta de colores
    $paleta = [
        ['border' => '#0d6efd', 'bg' => 'rgba(13,110,253,0.1)'],
        ['border' => '#ffc107', 'bg' => 'rgba(255,193,7,0.1)'],
        ['border' => '#198754', 'bg' => 'rgba(25,135,84,0.1)'],
        ['border' => '#dc3545', 'bg' => 'rgba(220,53,69,0.1)'],
        ['border' => '#6f42c1', 'bg' => 'rgba(111,66,193,0.1)'],
        ['border' => '#fd7e14', 'bg' => 'rgba(253,126,20,0.1)'],
        ['border' => '#20c997', 'bg' => 'rgba(32,201,151,0.1)'],
        ['border' => '#0dcaf0', 'bg' => 'rgba(13,202,240,0.1)'],
    ];

    // 4. Formatear etiquetas de mes en español
    $meses_es = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    $labels = array_map(function($m) use ($meses_es) {
        [$y, $mo] = explode('-', $m);
        return $meses_es[(int)$mo - 1] . ' ' . $y;
    }, $meses);

    // 5. Construir datasets
    $datasets = [];
    $i = 0;
    foreach ($tipos as $id => $nombre) {
        $color = $paleta[$i % count($paleta)];
        $dataSerie = [];
        foreach ($meses as $m) {
            $dataSerie[] = $matriz[$id][$m] ?? 0;
        }
        $datasets[] = [
            'label'           => $nombre,
            'data'            => $dataSerie,
            'borderColor'     => $color['border'],
            'backgroundColor' => $color['bg'],
            'borderWidth'     => 3,
            'fill'            => true,
            'tension'         => 0.35,
            'pointBackgroundColor' => $color['border'],
            'pointBorderColor'     => '#fff',
            'pointBorderWidth'     => 2,
            'pointRadius'          => 4,
            'pointHoverRadius'     => 7,
        ];
        $i++;
    }

    return ['meses' => $labels, 'datasets' => $datasets];
}
/**
 * Obtiene el conteo de solicitudes por estado según el rol del usuario
 * y opcionalmente filtrado por rango de fechas.
 *
 * @param string|null $fecha_desde  YYYY-MM-DD
 * @param string|null $fecha_hasta  YYYY-MM-DD
 * @return array  Lista con: id_estado, estado_nombre, total
 */
function obtenerConteoSolicitudesPorEstado($fecha_desde = null, $fecha_hasta = null) {
    global $pdo;

    $filtros = ["s.deleted_at IS NULL"];
    $params = [];

    // Filtro por titular (si no es admin)
    if (!esAdmin()) {
        $titulares = $_SESSION['titulares'] ?? [];
        if (empty($titulares)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($titulares), '?'));
        $filtros[] = "s.id_titular IN ($placeholders)";
        foreach ($titulares as $t) {
            $params[] = $t;
        }
    }

    // Filtros por fecha
    if (!empty($fecha_desde)) {
        $filtros[] = "DATE(s.fecha_solicita) >= ?";
        $params[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $filtros[] = "DATE(s.fecha_solicita) <= ?";
        $params[] = $fecha_hasta;
    }

    $where_solicitud = implode(' AND ', $filtros);

    $sql = "SELECT e.id_estado, e.nombre AS estado_nombre, 
                   COUNT(s.id_solicitud) AS total
            FROM estado_solicitud e
            LEFT JOIN solicitud s 
                ON s.id_estado_actual = e.id_estado 
                AND $where_solicitud
            GROUP BY e.id_estado, e.nombre
            ORDER BY e.id_estado";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene el total de solicitudes en el rango.
 */
function contarTotalSolicitudesRango($fecha_desde = null, $fecha_hasta = null) {
    global $pdo;

    $filtros = ["s.deleted_at IS NULL"];
    $params = [];

    if (!esAdmin()) {
        $titulares = $_SESSION['titulares'] ?? [];
        if (empty($titulares)) return 0;
        $placeholders = implode(',', array_fill(0, count($titulares), '?'));
        $filtros[] = "s.id_titular IN ($placeholders)";
        foreach ($titulares as $t) {
            $params[] = $t;
        }
    }
    if (!empty($fecha_desde)) {
        $filtros[] = "DATE(s.fecha_solicita) >= ?";
        $params[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $filtros[] = "DATE(s.fecha_solicita) <= ?";
        $params[] = $fecha_hasta;
    }

    $where = "WHERE " . implode(' AND ', $filtros);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM solicitud s $where");
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

/**
 * Devuelve información visual (ícono, color) para un estado de solicitud.
 */
function infoEstadoSolicitud($nombre_estado) {
    $mapa = [
        'Nueva'                => ['color' => 'secondary', 'icon' => 'fa-file',          'label' => 'Nueva'],
        'Solicitud_ingresado'  => ['color' => 'info',      'icon' => 'fa-inbox',         'label' => 'Ingresado'],
        'Solicitud_evaluacion' => ['color' => 'warning',   'icon' => 'fa-hourglass-half','label' => 'En Evaluación'],
        'Solicitud_aprobado'   => ['color' => 'success',   'icon' => 'fa-check-circle',  'label' => 'Aprobado'],
        'Solicitud_rechazada'  => ['color' => 'danger',    'icon' => 'fa-times-circle',  'label' => 'Rechazado'],
        'Solicitud_cancelado'  => ['color' => 'dark',      'icon' => 'fa-ban',           'label' => 'Cancelado'],
    ];
    return $mapa[$nombre_estado] ?? ['color' => 'secondary', 'icon' => 'fa-file', 'label' => $nombre_estado];
}

/**
 * Obtiene las últimas N solicitudes accesibles para el usuario,
 * opcionalmente filtradas por rango de fecha.
 */
function obtenerUltimasSolicitudes($limite = 5, $fecha_desde = null, $fecha_hasta = null) {
    global $pdo;
    $limite = (int)$limite;

    $filtros = ["s.deleted_at IS NULL"];
    $params = [];

    if (!esAdmin()) {
        $titulares = $_SESSION['titulares'] ?? [];
        if (empty($titulares)) return [];
        $placeholders = implode(',', array_fill(0, count($titulares), '?'));
        $filtros[] = "s.id_titular IN ($placeholders)";
        foreach ($titulares as $t) {
            $params[] = $t;
        }
    }
    if (!empty($fecha_desde)) {
        $filtros[] = "DATE(s.fecha_solicita) >= ?";
        $params[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $filtros[] = "DATE(s.fecha_solicita) <= ?";
        $params[] = $fecha_hasta;
    }

    $where = "WHERE " . implode(' AND ', $filtros);

    $sql = "SELECT s.id_solicitud, s.codigo_control_empresa, s.fecha_solicita,
                   p.nombre AS producto_nombre,
                   t.nombre AS titular_nombre,
                   e.nombre AS estado_nombre,
                   tt.nombre AS tipo_tramite_nombre
            FROM solicitud s
            LEFT JOIN producto p ON s.id_producto = p.id_producto
            LEFT JOIN titular t ON s.id_titular = t.id_titular
            LEFT JOIN estado_solicitud e ON s.id_estado_actual = e.id_estado
            LEFT JOIN tipo_tramite tt ON s.id_tipo_tramite = tt.id_tipo_tramite
            $where
            ORDER BY s.fecha_solicita DESC
            LIMIT $limite";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene las solicitudes agrupadas por mes dentro del rango (para gráfico de línea).
 */
function obtenerSolicitudesPorMes($fecha_desde = null, $fecha_hasta = null) {
    global $pdo;

    $filtros = ["s.deleted_at IS NULL"];
    $params = [];

    if (!esAdmin()) {
        $titulares = $_SESSION['titulares'] ?? [];
        if (empty($titulares)) return [];
        $placeholders = implode(',', array_fill(0, count($titulares), '?'));
        $filtros[] = "s.id_titular IN ($placeholders)";
        foreach ($titulares as $t) {
            $params[] = $t;
        }
    }
    if (!empty($fecha_desde)) {
        $filtros[] = "DATE(s.fecha_solicita) >= ?";
        $params[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $filtros[] = "DATE(s.fecha_solicita) <= ?";
        $params[] = $fecha_hasta;
    }

    $where = "WHERE " . implode(' AND ', $filtros);

    $sql = "SELECT DATE_FORMAT(s.fecha_solicita, '%Y-%m') AS mes,
                   COUNT(*) AS total
            FROM solicitud s
            $where
            GROUP BY mes
            ORDER BY mes ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================================
// FUNCIONES FINANCIERAS (DASHBOARD)
// ============================================================

/**
 * Total de ingresos agrupados por moneda en el rango de fechas.
 * @param string|null $estado_factura  'factura_emitida', 'factura_pagada' o null
 */
function obtenerIngresosPorMoneda($fecha_desde = null, $fecha_hasta = null, $estado_factura = null) {
    global $pdo;
    $filtros = ["f.deleted_at IS NULL"];
    $params  = [];

    if (!empty($fecha_desde)) {
        $filtros[] = "DATE(f.fecha_factura) >= ?";
        $params[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $filtros[] = "DATE(f.fecha_factura) <= ?";
        $params[] = $fecha_hasta;
    }
    if (!empty($estado_factura)) {
        $filtros[] = "ef.nombre = ?";
        $params[] = $estado_factura;
    }

    $where = "WHERE " . implode(' AND ', $filtros);

    $sql = "SELECT f.moneda, COALESCE(SUM(f.monto_total), 0) AS total
            FROM factura f
            LEFT JOIN estado_factura ef ON f.id_estado_factura = ef.id_estado_factura
            $where
            GROUP BY f.moneda";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultado = ['NIO' => 0.0, 'USD' => 0.0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $resultado[$fila['moneda']] = (float)$fila['total'];
    }
    return $resultado;
}

/**
 * Ingresos agrupados por tipo de trámite y moneda.
 */
/**
 * Ingresos agrupados por tipo de trámite y moneda,
 * filtrado por fecha de factura.
 */
function obtenerIngresosPorTipoTramite($fecha_desde = null, $fecha_hasta = null, $estado_factura = null) {
    global $pdo;

    error_log("[DEBUG tramite] desde=$fecha_desde hasta=$fecha_hasta estado=$estado_factura");

    $filtros = ["f.deleted_at IS NULL", "d.id_solicitud IS NOT NULL"];
    $params  = [];

    if (!empty($fecha_desde)) {
        $filtros[] = "DATE(f.fecha_factura) >= ?";
        $params[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $filtros[] = "DATE(f.fecha_factura) <= ?";
        $params[] = $fecha_hasta;
    }
    if (!empty($estado_factura)) {
        $filtros[] = "ef.nombre = ?";
        $params[] = $estado_factura;
    }

    $where = "WHERE " . implode(' AND ', $filtros);

    $sql = "SELECT tt.id_tipo_tramite, tt.nombre AS tipo_tramite_nombre, 
                   f.moneda,
                   COALESCE(SUM(d.subtotal), 0) AS total,
                   COUNT(DISTINCT d.id_solicitud) AS cantidad
            FROM factura f
            JOIN factura_detalle d ON d.id_factura = f.id_factura
            LEFT JOIN estado_factura ef ON f.id_estado_factura = ef.id_estado_factura
            JOIN solicitud s ON d.id_solicitud = s.id_solicitud
            JOIN tipo_tramite tt ON s.id_tipo_tramite = tt.id_tipo_tramite
            $where
            GROUP BY tt.id_tipo_tramite, tt.nombre, f.moneda
            ORDER BY tt.nombre, f.moneda";

    error_log("[DEBUG tramite] SQL: $sql");
    error_log("[DEBUG tramite] Params: " . print_r($params, true));

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("[DEBUG tramite] Filas devueltas: " . print_r($rows, true));

    return $rows;
}

function obtenerIngresosPorTipoModificacion($fecha_desde = null, $fecha_hasta = null, $estado_factura = null) {
    global $pdo;

    $filtros = ["f.deleted_at IS NULL", "d.id_solicitud IS NOT NULL", "s.id_tipo_modificacion IS NOT NULL"];
    $params  = [];

    if (!empty($fecha_desde)) {
        $filtros[] = "DATE(f.fecha_factura) >= ?";
        $params[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $filtros[] = "DATE(f.fecha_factura) <= ?";
        $params[] = $fecha_hasta;
    }
    if (!empty($estado_factura)) {
        $filtros[] = "ef.nombre = ?";
        $params[] = $estado_factura;
    }

    $where = "WHERE " . implode(' AND ', $filtros);

    $sql = "SELECT tm.id_tipo_modificacion, tm.nombre AS tipo_modificacion_nombre, 
                   f.moneda,
                   COALESCE(SUM(d.subtotal), 0) AS total,
                   COUNT(DISTINCT d.id_solicitud) AS cantidad
            FROM factura f
            JOIN factura_detalle d ON d.id_factura = f.id_factura
            LEFT JOIN estado_factura ef ON f.id_estado_factura = ef.id_estado_factura
            JOIN solicitud s ON d.id_solicitud = s.id_solicitud
            JOIN tipo_modificacion tm ON s.id_tipo_modificacion = tm.id_tipo_modificacion
            $where
            GROUP BY tm.id_tipo_modificacion, tm.nombre, f.moneda
            ORDER BY tm.nombre, f.moneda";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
/**
 * Cuenta facturas y devuelve el monto total por moneda (para KPIs).
 * @return array ['cantidad' => int, 'total_nio' => float, 'total_usd' => float]
 */
function obtenerResumenFacturas($fecha_desde = null, $fecha_hasta = null) {
    global $pdo;
    $filtros = ["deleted_at IS NULL"];
    $params  = [];

    if (!empty($fecha_desde)) {
        $filtros[] = "DATE(fecha_factura) >= ?";
        $params[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $filtros[] = "DATE(fecha_factura) <= ?";
        $params[] = $fecha_hasta;
    }

    $where = "WHERE " . implode(' AND ', $filtros);
    $sql = "SELECT COUNT(*) AS cantidad FROM factura $where";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $cantidad = (int)$stmt->fetchColumn();

    $ingresos = obtenerIngresosPorMoneda($fecha_desde, $fecha_hasta);

    return [
        'cantidad'  => $cantidad,
        'total_nio' => $ingresos['NIO'] ?? 0,
        'total_usd' => $ingresos['USD'] ?? 0,
    ];
}
// ============================================================
// MÓDULO DE FACTURACIÓN
// ============================================================

/**
 * Obtiene el siguiente número consecutivo de factura (thread-safe con transacción).
 * Formato devuelto: F-000001, F-000002, ...
 * 
 * IMPORTANTE: debe llamarse DENTRO de una transacción.
 */
function obtenerSiguienteNumeroFactura() {
    global $pdo;

    // Lock de la fila para evitar condiciones de carrera
    $stmt = $pdo->prepare("SELECT id_consecutivo, prefijo, ultimo_numero 
                           FROM consecutivo 
                           WHERE tipo = 'FACTURA' FOR UPDATE");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        // Crear si no existe
        $pdo->exec("INSERT INTO consecutivo (tipo, prefijo, ultimo_numero) VALUES ('FACTURA', 'F-', 0)");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $nuevo_numero = (int)$row['ultimo_numero'] + 1;
    $prefijo      = $row['prefijo'] ?: 'F-';

    $stmt = $pdo->prepare("UPDATE consecutivo SET ultimo_numero = :n WHERE id_consecutivo = :id");
    $stmt->execute([':n' => $nuevo_numero, ':id' => $row['id_consecutivo']]);

    return $prefijo . str_pad($nuevo_numero, 6, '0', STR_PAD_LEFT);
}

/**
 * Devuelve las solicitudes en estado Aprobado listas para facturar.
 * @return array
 */
function obtenerSolicitudesAprobadasParaFacturar() {
    global $pdo;

    $where = "WHERE s.deleted_at IS NULL 
              AND e.nombre = 'Solicitud_aprobado'";
    $params = [];

    // Restricción por titular si no es admin
    if (!esAdmin()) {
        $titulares = $_SESSION['titulares'] ?? [];
        if (empty($titulares)) return [];
        $placeholders = implode(',', array_fill(0, count($titulares), '?'));
        $where .= " AND s.id_titular IN ($placeholders)";
        $params = $titulares;
    }

    $sql = "SELECT s.id_solicitud, s.codigo_control_empresa, s.fecha_solicita,
                   s.id_titular,
                   p.nombre AS producto_nombre,
                   p.marca AS producto_marca,
                   t.nombre AS titular_nombre,
                   t.direccion_texto AS titular_direccion,
                   t.telefono AS titular_telefono,
                   tt.nombre AS tipo_tramite_nombre,
                   tm.nombre AS tipo_modificacion_nombre,
                   tl.nombre AS tipo_licencia_nombre,
                   e.nombre  AS estado_nombre
            FROM solicitud s
            LEFT JOIN producto p ON s.id_producto = p.id_producto
            LEFT JOIN titular t ON s.id_titular = t.id_titular
            LEFT JOIN estado_solicitud e ON s.id_estado_actual = e.id_estado
            LEFT JOIN tipo_tramite tt ON s.id_tipo_tramite = tt.id_tipo_tramite
            LEFT JOIN tipo_modificacion tm ON s.id_tipo_modificacion = tm.id_tipo_modificacion
            LEFT JOIN tipo_licencia tl ON s.id_tipo_licencia = tl.id_tipo_licencia
            $where
            ORDER BY s.fecha_solicita ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Resolver producto propuesto si es trámite nuevo
    foreach ($datos as &$row) {
        if (empty($row['producto_nombre'])) {
            $stmtP = $pdo->prepare("SELECT producto_datos_propuestos FROM solicitud WHERE id_solicitud = :id");
            $stmtP->execute([':id' => $row['id_solicitud']]);
            $json = $stmtP->fetchColumn();
            if ($json) {
                $prop = json_decode($json, true);
                if (is_array($prop) && !empty($prop['nombre'])) {
                    $row['producto_nombre'] = $prop['nombre'];
                }
            }
            if (empty($row['producto_nombre'])) {
                $row['producto_nombre'] = '(Producto no asignado)';
            }
        }
    }
    unset($row);

    return $datos;
}
/**
 * Crea una factura con sus líneas de detalle y marca las solicitudes como Cobradas.
 * 
 * @param array $datos  ['nombre', 'direccion', 'telefono', 'moneda', 'id_titular']
 * @param array $items  Cada item: ['id_solicitud', 'descripcion', 'monto_unitario', 'cantidad']
 * @return array ['success' => bool, 'message' => string, 'id_factura' => int, 'numero_factura' => string]
 */

function crearFactura($datos, $items) {
    global $pdo;

    if (empty($items)) {
        return ['success' => false, 'message' => 'Debes agregar al menos una solicitud.'];
    }
    if (empty($datos['nombre'])) {
        return ['success' => false, 'message' => 'El nombre del cliente es obligatorio.'];
    }

    $moneda = in_array($datos['moneda'] ?? 'NIO', ['NIO', 'USD']) ? $datos['moneda'] : 'NIO';
    $id_titular = !empty($datos['id_titular']) ? (int)$datos['id_titular'] : null;

    try {
        $pdo->beginTransaction();

        $numero_factura = obtenerSiguienteNumeroFactura();

        $monto_total = 0;
        foreach ($items as $it) {
            $cantidad = max(1, (int)($it['cantidad'] ?? 1));
            $monto    = (float)($it['monto_unitario'] ?? 0);
            $monto_total += $cantidad * $monto;
        }

        $id_estado_emitida = obtenerIdEstadoFactura('factura_emitida');
        if (!$id_estado_emitida) {
            throw new Exception('Estado "factura_emitida" no configurado.');
        }

        $stmt = $pdo->prepare("
            INSERT INTO factura 
                (numero_factura, fecha_factura, nombre, direccion, telefono, 
                 moneda, monto_total, id_estado_factura, id_titular, id_usuario_crea)
            VALUES 
                (:numero, NOW(), :nombre, :direccion, :telefono, 
                 :moneda, :total, :id_estado_factura, :id_titular, :id_usuario)
        ");
        $ok = $stmt->execute([
            ':numero'             => $numero_factura,
            ':nombre'             => $datos['nombre'],
            ':direccion'          => $datos['direccion'] ?? null,
            ':telefono'           => $datos['telefono'] ?? null,
            ':moneda'             => $moneda,
            ':total'              => $monto_total,
            ':id_estado_factura'  => $id_estado_emitida,
            ':id_titular'         => $id_titular,
            ':id_usuario'         => $_SESSION['usuario_id'] ?? null,
        ]);

        if (!$ok) {
            $err = $stmt->errorInfo();
            throw new Exception('Fallo el INSERT en Factura: ' . ($err[2] ?? 'desconocido'));
        }

        $id_factura = (int)$pdo->lastInsertId();
        if ($id_factura <= 0) {
            throw new Exception('No se pudo obtener el ID de la factura.');
        }

        $stmtDet = $pdo->prepare("
            INSERT INTO factura_detalle 
                (id_factura, id_solicitud, descripcion, monto_unitario, cantidad, subtotal)
            VALUES 
                (:id_factura, :id_solicitud, :descripcion, :monto_unitario, :cantidad, :subtotal)
        ");

        $id_estado_cobrada = obtenerIdEstado('Solicitud_cobrada');
        if (!$id_estado_cobrada) {
            throw new Exception('El estado "Solicitud_cobrada" no existe.');
        }

        $stmtUpd = $pdo->prepare("UPDATE solicitud 
                                  SET id_estado_actual = :id_estado 
                                  WHERE id_solicitud = :id");

        foreach ($items as $it) {
            $id_solicitud = (int)$it['id_solicitud'];
            $cantidad     = max(1, (int)($it['cantidad'] ?? 1));
            $monto_unit   = (float)($it['monto_unitario'] ?? 0);
            $subtotal     = $cantidad * $monto_unit;

            $okDet = $stmtDet->execute([
                ':id_factura'     => $id_factura,
                ':id_solicitud'   => $id_solicitud,
                ':descripcion'    => $it['descripcion'] ?? 'Servicio',
                ':monto_unitario' => $monto_unit,
                ':cantidad'       => $cantidad,
                ':subtotal'       => $subtotal,
            ]);

            if (!$okDet) {
                $err = $stmtDet->errorInfo();
                throw new Exception("Fallo INSERT Detalle: " . ($err[2] ?? 'desconocido'));
            }

            $stmtUpd->execute([
                ':id_estado' => $id_estado_cobrada,
                ':id'        => $id_solicitud
            ]);
        }

        $pdo->commit();

        return [
            'success'         => true,
            'message'         => "Factura {$numero_factura} creada correctamente.",
            'id_factura'      => $id_factura,
            'numero_factura'  => $numero_factura
        ];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}
/**
 * Obtiene el ID de un estado de factura por su nombre.
 */
function obtenerIdEstadoFactura($nombre) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id_estado_factura FROM estado_factura WHERE nombre = :nombre");
    $stmt->execute([':nombre' => $nombre]);
    return $stmt->fetchColumn();
}

/**
 * Marca una factura como PAGADA y actualiza sus solicitudes asociadas
 * al estado "Solicitud_pagada".
 * 
 * @param int $id_factura
 * @param int $id_usuario
 * @return array ['success' => bool, 'message' => string, 'solicitudes_afectadas' => int]
 */
function marcarFacturaComoPagada($id_factura, $id_usuario) {
    global $pdo;

    try {
        $pdo->beginTransaction();

        // 1. Obtener factura
        $stmt = $pdo->prepare("
            SELECT f.*, ef.nombre AS estado_nombre
            FROM factura f
            LEFT JOIN estado_factura ef ON f.id_estado_factura = ef.id_estado_factura
            WHERE f.id_factura = :id AND f.deleted_at IS NULL
        ");
        $stmt->execute([':id' => $id_factura]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$factura) {
            throw new Exception('Factura no encontrada.');
        }

        if ($factura['estado_nombre'] === 'factura_pagada') {
            throw new Exception('Esta factura ya fue marcada como pagada.');
        }

        // 2. Actualizar estado de la factura
        $id_pagada = obtenerIdEstadoFactura('factura_pagada');
        if (!$id_pagada) {
            throw new Exception('Estado "factura_pagada" no configurado.');
        }

        $stmt = $pdo->prepare("UPDATE factura SET id_estado_factura = :id_estado WHERE id_factura = :id");
        $stmt->execute([':id_estado' => $id_pagada, ':id' => $id_factura]);

        // 3. Obtener las solicitudes asociadas
        $stmt = $pdo->prepare("
            SELECT id_solicitud, id_estado_actual AS id_estado_anterior
            FROM solicitud s
            WHERE s.id_solicitud IN (
                SELECT id_solicitud FROM factura_detalle 
                WHERE id_factura = :id AND id_solicitud IS NOT NULL
            )
        ");
        $stmt->execute([':id' => $id_factura]);
        $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $id_solicitud_pagada = obtenerIdEstado('Solicitud_pagada');
        if (!$id_solicitud_pagada) {
            throw new Exception('Estado "Solicitud_pagada" no configurado.');
        }

        $afectadas = 0;
        $stmtUpd = $pdo->prepare("UPDATE solicitud SET id_estado_actual = :id_estado WHERE id_solicitud = :id");
        $stmtRev = $pdo->prepare("
            INSERT INTO solicitud_revision 
            (id_solicitud, numero_revision, id_usuario_evaluador, fecha_revision,
             id_estado_anterior, id_estado_nuevo, comentario_evaluador)
            VALUES 
            (:id_solicitud,
             (SELECT COALESCE(MAX(numero_revision), 0) + 1 FROM solicitud_revision sr WHERE sr.id_solicitud = :id_solicitud_sub),
             :id_usuario, NOW(),
             :id_estado_anterior, :id_estado_nuevo, :comentario)
        ");

        foreach ($solicitudes as $sol) {
            $stmtUpd->execute([
                ':id_estado' => $id_solicitud_pagada,
                ':id'        => $sol['id_solicitud']
            ]);

            $stmtRev->execute([
                ':id_solicitud'        => $sol['id_solicitud'],
                ':id_solicitud_sub'    => $sol['id_solicitud'],
                ':id_usuario'          => $id_usuario,
                ':id_estado_anterior'  => $sol['id_estado_anterior'],
                ':id_estado_nuevo'     => $id_solicitud_pagada,
                ':comentario'          => 'Factura ' . $factura['numero_factura'] . ' pagada.'
            ]);

            $afectadas++;
        }

        $pdo->commit();
        return [
            'success'                => true,
            'message'                => "Factura marcada como pagada. {$afectadas} solicitud(es) actualizada(s).",
            'solicitudes_afectadas'  => $afectadas
        ];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Obtiene una factura por ID con sus detalles.
 */
function obtenerFactura($id_factura) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT f.*, 
               t.nombre AS titular_nombre, 
               u.nombre_usuario AS usuario_creador,
               ef.nombre AS estado_nombre,
               ef.id_estado_factura
        FROM factura f
        LEFT JOIN titular t ON f.id_titular = t.id_titular
        LEFT JOIN usuario u ON f.id_usuario_crea = u.id_usuario
        LEFT JOIN estado_factura ef ON f.id_estado_factura = ef.id_estado_factura
        WHERE f.id_factura = :id AND f.deleted_at IS NULL
    ");
    $stmt->execute([':id' => $id_factura]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$factura) return null;

    $stmt = $pdo->prepare("
        SELECT d.*, s.codigo_control_empresa
        FROM factura_detalle d
        LEFT JOIN solicitud s ON d.id_solicitud = s.id_solicitud
        WHERE d.id_factura = :id
        ORDER BY d.id_detalle ASC
    ");
    $stmt->execute([':id' => $id_factura]);
    $factura['detalles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $factura;
}

/**
 * Lista facturas con paginación.
 */
function buscarFacturas($busqueda = '', $pagina = 1, $limite = 15) {
    global $pdo;
    $offset = ($pagina - 1) * $limite;
    $params = [];
    $where = "WHERE f.deleted_at IS NULL";

    if (!empty($busqueda)) {
        $where .= " AND (f.numero_factura LIKE ? OR f.nombre LIKE ?)";
        $like = "%{$busqueda}%";
        $params[] = $like;
        $params[] = $like;
    }

    $sqlCount = "SELECT COUNT(*) FROM factura f $where";
    $stmt = $pdo->prepare($sqlCount);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

     $sql = "SELECT f.*, 
                   u.nombre_usuario AS usuario_creador,
                   ef.nombre AS estado_nombre
            FROM factura f
            LEFT JOIN usuario u ON f.id_usuario_crea = u.id_usuario
            LEFT JOIN estado_factura ef ON f.id_estado_factura = ef.id_estado_factura
            $where
            ORDER BY f.fecha_factura DESC
            LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);
    $idx = 1;
    foreach ($params as $p) {
        $stmt->bindValue($idx++, $p);
    }
    $stmt->bindValue($idx++, (int)$limite, PDO::PARAM_INT);
    $stmt->bindValue($idx, (int)$offset, PDO::PARAM_INT);
    $stmt->execute();

    return ['datos' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
}

/**
 * Formatea moneda con símbolo.
 */
function formatearMoneda($monto, $moneda) {
    $simbolo = ($moneda === 'USD') ? 'US$' : 'C$';
    return $simbolo . ' ' . number_format((float)$monto, 2, '.', ',');
}

/**
 * Obtiene un resumen de facturas agrupadas por estado (emitida/pagada) y moneda.
 * @return array [
 *   'factura_emitida' => ['cantidad' => X, 'NIO' => X, 'USD' => X],
 *   'factura_pagada'  => ['cantidad' => X, 'NIO' => X, 'USD' => X],
 * ]
 */
/**
 * Resumen de facturas agrupadas por estado (emitida/pagada) y moneda,
 * filtrado por fecha de factura.
 */
function obtenerResumenFacturasPorEstado($fecha_desde = null, $fecha_hasta = null) {
    global $pdo;

    error_log("[DEBUG factura] fecha_desde=" . var_export($fecha_desde, true) 
            . " fecha_hasta=" . var_export($fecha_hasta, true));

    $filtros = ["f.deleted_at IS NULL"];
    $params  = [];

    if (!empty($fecha_desde)) {
        $filtros[] = "DATE(f.fecha_factura) >= ?";
        $params[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $filtros[] = "DATE(f.fecha_factura) <= ?";
        $params[] = $fecha_hasta;
    }

    $where = "WHERE " . implode(' AND ', $filtros);

    $sql = "SELECT ef.nombre AS estado, f.moneda,
                   COUNT(*) AS cantidad,
                   COALESCE(SUM(f.monto_total), 0) AS total
            FROM factura f
            LEFT JOIN estado_factura ef ON f.id_estado_factura = ef.id_estado_factura
            $where
            GROUP BY ef.nombre, f.moneda";

    error_log("[DEBUG factura] SQL: $sql");
    error_log("[DEBUG factura] Params: " . print_r($params, true));

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("[DEBUG factura] Filas devueltas: " . print_r($rows, true));

    $resultado = [
        'factura_emitida' => ['cantidad' => 0, 'NIO' => 0.0, 'USD' => 0.0],
        'factura_pagada'  => ['cantidad' => 0, 'NIO' => 0.0, 'USD' => 0.0],
    ];

    foreach ($rows as $fila) {
        $estado = $fila['estado'] ?: 'factura_emitida';
        if (!isset($resultado[$estado])) {
            $resultado[$estado] = ['cantidad' => 0, 'NIO' => 0.0, 'USD' => 0.0];
        }
        $resultado[$estado]['cantidad'] += (int)$fila['cantidad'];
        $resultado[$estado][$fila['moneda']] = (float)$fila['total'];
    }

    error_log("[DEBUG factura] Resultado final: " . print_r($resultado, true));

    return $resultado;
}
?>