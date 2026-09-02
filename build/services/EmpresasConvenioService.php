<?php
/**
 * Servicio para gestionar empresas en convenio - VERSIÓN CORREGIDA
 * Contiene toda la lógica de negocio para el manejo de empresas
 */

if (!defined('CLAUT_ACCESS')) {
    die('Acceso no autorizado');
}

class EmpresasConvenioService {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Obtener todas las empresas en convenio con estadísticas
     */
    public function obtenerEmpresas($limite = null, $incluirEstadisticas = false) {
        $sql = "SELECT e.*, 
                       COUNT(DISTINCT u.id) as total_empleados,
                       COUNT(DISTINCT cm.comite_id) as comites_participando,
                       MAX(u.fecha_registro) as ultimo_registro_empleado
                FROM empresas_convenio e
                LEFT JOIN usuarios_perfil u ON e.id = u.empresa_id AND u.estado = 'activo'
                LEFT JOIN comite_miembros cm ON u.id = cm.usuario_id AND cm.estado = 'activo'
                WHERE e.activo = 1
                GROUP BY e.id, e.nombre, e.descripcion, e.sitio_web, e.telefono, 
                         e.email, e.direccion, e.logo_url, e.activo, 
                         e.fecha_registro, e.fecha_actualizacion, e.sector
                ORDER BY e.nombre";
        
        if ($limite && is_numeric($limite)) {
            $sql .= " LIMIT " . (int)$limite;
        }
        
        try {
            $empresas = $this->db->select($sql);
            
            // Convertir números a enteros
            foreach ($empresas as &$empresa) {
                $empresa['total_empleados'] = (int)($empresa['total_empleados'] ?? 0);
                $empresa['comites_participando'] = (int)($empresa['comites_participando'] ?? 0);
                
                // Asegurar que los campos necesarios existan
                $empresa['descripcion'] = $empresa['descripcion'] ?? '';
                $empresa['logo'] = $empresa['logo'] ?? '';
                $empresa['sitio_web'] = $empresa['sitio_web'] ?? '';
                $empresa['website'] = $empresa['sitio_web']; // Alias para compatibilidad
            }
            unset($empresa); // Romper la referencia
            
            if ($incluirEstadisticas) {
                $estadisticas = $this->obtenerEstadisticasGenerales($empresas);
                return [
                    'empresas' => $empresas,
                    'estadisticas' => $estadisticas
                ];
            }
            
            return $empresas;
            
        } catch (Exception $e) {
            error_log("Error obteniendo empresas: " . $e->getMessage());
            throw new Exception("Error al obtener las empresas: " . $e->getMessage());
        }
    }
    
    /**
     * Obtener una empresa específica con detalles completos
     */
    public function obtenerEmpresaPorId($id) {
        try {
            // Obtener datos básicos de la empresa
            $empresa = $this->db->selectOne(
                "SELECT e.*, 
                        COUNT(DISTINCT u.id) as total_empleados,
                        COUNT(DISTINCT cm.comite_id) as comites_participando
                 FROM empresas_convenio e
                 LEFT JOIN usuarios_perfil u ON e.id = u.empresa_id AND u.estado = 'activo'
                 LEFT JOIN comite_miembros cm ON u.id = cm.usuario_id AND cm.estado = 'activo'
                 WHERE e.id = ? AND e.activo = 1
                 GROUP BY e.id",
                [$id]
            );
            
            if (!$empresa) {
                return null;
            }
            
            // Convertir números y agregar campos
            $empresa['total_empleados'] = (int)($empresa['total_empleados'] ?? 0);
            $empresa['comites_participando'] = (int)($empresa['comites_participando'] ?? 0);
            $empresa['website'] = $empresa['sitio_web'] ?? '';
            
            // Obtener empleados de la empresa
            $empresa['empleados'] = $this->obtenerEmpleadosEmpresa($id);
            
            // Obtener comités en los que participa
            $empresa['comites'] = $this->obtenerComitesEmpresa($id);
            
            return $empresa;
            
        } catch (Exception $e) {
            error_log("Error obteniendo empresa por ID: " . $e->getMessage());
            throw new Exception("Error al obtener la empresa: " . $e->getMessage());
        }
    }
    
    /**
     * Crear nueva empresa en convenio
     */
    public function crearEmpresa($datos) {
        try {
            // Validar datos
            $this->validarDatosEmpresa($datos);
            
            // Verificar si ya existe
            if ($this->existeEmpresa($datos['nombre'])) {
                throw new Exception('Ya existe una empresa con este nombre');
            }
            
            // Preparar datos
            $datosLimpios = $this->sanitizarDatosEmpresa($datos);
            
            $this->db->beginTransaction();
            
            $sql = "INSERT INTO empresas_convenio (nombre, descripcion, sitio_web, telefono, email, direccion, logo, estado, razon_social, rfc, sector) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'activo', ?, ?, ?)";
            
            $params = [
                $datosLimpios['nombre'],
                $datosLimpios['descripcion'],
                $datosLimpios['website'],
                $datosLimpios['telefono'],
                $datosLimpios['email'],
                $datosLimpios['direccion'],
                $datosLimpios['logo'],
                $datosLimpios['razon_social'] ?? '',
                $datosLimpios['rfc'] ?? '',
                $datosLimpios['sector'] ?? 'Automotriz'
            ];
            
            $empresaId = $this->db->insert($sql, $params);
            
            $this->db->commit();
            
            return $this->obtenerEmpresaPorId($empresaId);
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error creando empresa: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Actualizar empresa existente
     */
    public function actualizarEmpresa($id, $datos) {
        try {
            // Verificar que existe
            $empresa = $this->obtenerEmpresaPorId($id);
            if (!$empresa) {
                throw new Exception('Empresa no encontrada');
            }
            
            // Validar datos
            $this->validarDatosEmpresa($datos, false);
            
            // Verificar nombre único (si se está cambiando)
            if (isset($datos['nombre']) && $datos['nombre'] !== $empresa['nombre']) {
                if ($this->existeEmpresa($datos['nombre'], $id)) {
                    throw new Exception('Ya existe una empresa con este nombre');
                }
            }
            
            // Preparar campos a actualizar
            $campos = [];
            $valores = [];
            $camposPermitidos = [
                'nombre', 'descripcion', 'website' => 'sitio_web', 'telefono', 
                'email', 'direccion', 'logo', 'razon_social', 'rfc', 'sector'
            ];
            
            foreach ($camposPermitidos as $campoInput => $campoDb) {
                // Si es un mapeo (website => sitio_web)
                if (is_string($campoInput)) {
                    if (isset($datos[$campoInput])) {
                        $campos[] = "$campoDb = ?";
                        $valores[] = $this->sanitizarCampo($datos[$campoInput]);
                    }
                } else {
                    // Campo directo
                    if (isset($datos[$campoDb])) {
                        $campos[] = "$campoDb = ?";
                        $valores[] = $this->sanitizarCampo($datos[$campoDb]);
                    }
                }
            }
            
            if (empty($campos)) {
                throw new Exception('No hay datos para actualizar');
            }
            
            $this->db->beginTransaction();
            
            $valores[] = $id;
            $sql = "UPDATE empresas_convenio SET " . implode(', ', $campos) . " WHERE id = ?";
            
            $this->db->update($sql, $valores);
            
            $this->db->commit();
            
            return $this->obtenerEmpresaPorId($id);
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error actualizando empresa: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Eliminar empresa del convenio (soft delete)
     */
    public function eliminarEmpresa($id) {
        try {
            $empresa = $this->obtenerEmpresaPorId($id);
            if (!$empresa) {
                throw new Exception('Empresa no encontrada');
            }
            
            // Verificar si tiene empleados activos
            $empleadosActivos = $this->db->selectOne(
                "SELECT COUNT(*) as total FROM usuarios_perfil WHERE empresa_id = ? AND estado = 'activo'",
                [$id]
            );
            
            if ($empleadosActivos['total'] > 0) {
                throw new Exception('No se puede eliminar la empresa porque tiene empleados activos');
            }
            
            $this->db->beginTransaction();
            
            $this->db->update("UPDATE empresas_convenio SET estado = 'inactivo' WHERE id = ?", [$id]);
            
            $this->db->commit();
            
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error eliminando empresa: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obtener estadísticas generales
     */
    private function obtenerEstadisticasGenerales($empresas) {
        return [
            'total_empresas' => count($empresas),
            'total_empleados' => array_sum(array_column($empresas, 'total_empleados')),
            'empresas_con_comites' => count(array_filter($empresas, function($e) { 
                return $e['comites_participando'] > 0; 
            })),
            'promedio_empleados' => count($empresas) > 0 ? 
                round(array_sum(array_column($empresas, 'total_empleados')) / count($empresas), 2) : 0
        ];
    }
    
    /**
     * Obtener empleados de una empresa
     */
    private function obtenerEmpleadosEmpresa($empresaId) {
        try {
            return $this->db->select(
                "SELECT u.id, u.nombre, u.apellido, u.email, u.telefono, u.rol, 
                        u.fecha_registro, u.avatar, u.puesto
                 FROM usuarios_perfil u 
                 WHERE u.empresa_id = ? AND u.estado = 'activo'
                 ORDER BY u.nombre, u.apellido",
                [$empresaId]
            );
        } catch (Exception $e) {
            error_log("Error obteniendo empleados: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener comités en los que participa una empresa
     */
    private function obtenerComitesEmpresa($empresaId) {
        try {
            return $this->db->select(
                "SELECT DISTINCT c.id, c.nombre, c.descripcion,
                        COUNT(cm.usuario_id) as miembros_empresa
                 FROM comites c
                 INNER JOIN comite_miembros cm ON c.id = cm.comite_id
                 INNER JOIN usuarios_perfil u ON cm.usuario_id = u.id
                 WHERE u.empresa_id = ? AND u.estado = 'activo' 
                       AND cm.estado = 'activo' AND c.estado = 'activo'
                 GROUP BY c.id, c.nombre, c.descripcion
                 ORDER BY c.nombre",
                [$empresaId]
            );
        } catch (Exception $e) {
            error_log("Error obteniendo comités: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Validar datos de empresa
     */
    private function validarDatosEmpresa($datos, $esCreacion = true) {
        if ($esCreacion && empty($datos['nombre'])) {
            throw new Exception('El nombre de la empresa es requerido');
        }
        
        if (isset($datos['email']) && !empty($datos['email']) && !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Formato de email inválido');
        }
        
        $websiteField = $datos['website'] ?? $datos['sitio_web'] ?? '';
        if (!empty($websiteField)) {
            // Agregar protocolo si no lo tiene
            if (!preg_match('/^https?:\/\//', $websiteField)) {
                $websiteField = 'http://' . $websiteField;
            }
            if (!filter_var($websiteField, FILTER_VALIDATE_URL)) {
                throw new Exception('Formato de URL inválido para el sitio web');
            }
        }
    }
    
    /**
     * Verificar si existe una empresa con el nombre dado
     */
    private function existeEmpresa($nombre, $excluirId = null) {
        try {
            $sql = "SELECT id FROM empresas_convenio WHERE nombre = ? AND activo = 1";
            $params = [$nombre];
            
            if ($excluirId) {
                $sql .= " AND id != ?";
                $params[] = $excluirId;
            }
            
            $empresa = $this->db->selectOne($sql, $params);
            return $empresa !== null;
            
        } catch (Exception $e) {
            error_log("Error verificando empresa existente: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sanitizar datos de empresa
     */
    private function sanitizarDatosEmpresa($datos) {
        $result = [
            'nombre' => $this->sanitizarCampo($datos['nombre'] ?? ''),
            'descripcion' => $this->sanitizarCampo($datos['descripcion'] ?? ''),
            'website' => $this->sanitizarCampo($datos['website'] ?? $datos['sitio_web'] ?? ''),
            'telefono' => $this->sanitizarCampo($datos['telefono'] ?? ''),
            'email' => $this->sanitizarCampo($datos['email'] ?? ''),
            'direccion' => $this->sanitizarCampo($datos['direccion'] ?? ''),
            'logo' => $this->sanitizarCampo($datos['logo'] ?? ''),
            'razon_social' => $this->sanitizarCampo($datos['razon_social'] ?? ''),
            'rfc' => $this->sanitizarCampo($datos['rfc'] ?? ''),
            'sector' => $this->sanitizarCampo($datos['sector'] ?? 'Automotriz')
        ];
        
        // Procesar URL del sitio web
        if (!empty($result['website']) && !preg_match('/^https?:\/\//', $result['website'])) {
            $result['website'] = 'http://' . $result['website'];
        }
        
        return $result;
    }
    
    /**
     * Sanitizar un campo individual
     */
    private function sanitizarCampo($valor) {
        return trim(strip_tags($valor));
    }
}
?>