<?php
/**
 * Página de detalles de evento individual para cargar en iframe
 */

// Configurar headers
header('Content-Type: text/html; charset=utf-8');

// Obtener ID del evento
$eventoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$eventoId) {
    die('<div class="p-8 text-center"><h2 class="text-red-600">Error: ID de evento no especificado</h2></div>');
}

// Incluir configuración de base de datos
require_once __DIR__ . '/config/database.php';

try {
    // Obtener datos del evento
    $database = Database::getInstance();
    $pdo = $database->getConnection();

    $sql = "SELECT * FROM eventos WHERE id = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$eventoId]);
    $evento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$evento) {
        die('<div class="p-8 text-center"><h2 class="text-red-600">Error: Evento no encontrado</h2></div>');
    }

    // DEBUG: Verificar campos de beneficios
    error_log("DEBUG evento_detalle.php - ID: {$eventoId}");
    error_log("DEBUG link_evento: " . ($evento['link_evento'] ?? 'NULL'));
    error_log("DEBUG link_mapa: " . ($evento['link_mapa'] ?? 'NULL'));
    error_log("DEBUG tiene_beneficio: " . ($evento['tiene_beneficio'] ?? 'NULL'));

    // Procesar datos del evento
    $fechaInicio = new DateTime($evento['fecha_inicio']);
    $fechaFin = $evento['fecha_fin'] ? new DateTime($evento['fecha_fin']) : null;
    $now = new DateTime();

    $estadoCalculado = 'proximo';
    if ($fechaInicio <= $now) {
        if ($fechaFin && $fechaFin < $now) {
            $estadoCalculado = 'finalizado';
        } else {
            $estadoCalculado = 'en_curso';
        }
    }

    $fechaFormateada = $fechaInicio->format('d/m/Y');
    $horaFormateada = $fechaInicio->format('H:i');
    $fechaFinFormateada = $fechaFin ? $fechaFin->format('d/m/Y H:i') : null;

} catch (Exception $e) {
    die('<div class="p-8 text-center"><h2 class="text-red-600">Error: No se pudo cargar el evento</h2><p>' . htmlspecialchars($e->getMessage()) . '</p></div>');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($evento['titulo']) ?> - Detalles</title>
    <link href="./dist/tailwind.css?v=20260909a" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --porsche-accent: #C7252B;
            --porsche-charcoal: #2d2d2d;
            --porsche-radius: 0.75rem;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
        }

        .hero-image {
            background: linear-gradient(135deg, var(--porsche-accent), #e53e3e);
            position: relative;
            overflow: hidden;
        }

        .hero-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.3) 100%);
            z-index: 1;
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .info-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(199, 37, 43, 0.15);
        }

        .btn-gradient {
            background: linear-gradient(135deg, var(--porsche-accent), #e53e3e);
            box-shadow: 0 4px 15px rgba(199, 37, 43, 0.3);
            transition: all 0.3s ease;
        }

        .btn-gradient:hover {
            background: linear-gradient(135deg, #b01e24, var(--porsche-accent));
            box-shadow: 0 6px 20px rgba(199, 37, 43, 0.4);
            transform: translateY(-1px);
        }

        .progress-ring {
            background: conic-gradient(var(--porsche-accent) <?= min($porcentaje, 100) * 3.6 ?>deg, #e2e8f0 0deg);
        }

        .animate-in {
            animation: slideInUp 0.6s ease-out;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .badge-floating {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        /* === REGISTRATION MODAL SCROLL FIXES === */

        /* Modal container with proper scroll */
        #registrationModal {
            overflow: auto !important;
        }

        /* Modal content wrapper with scroll capability */
        #registrationModal .flex.items-center.justify-center {
            overflow-y: auto !important;
            padding: 1rem;
            align-items: flex-start !important;
            min-height: 100vh;
        }

        /* Modal dialog with max height and internal scroll */
        #registrationModal .bg-white.rounded-xl {
            max-height: calc(100vh - 2rem) !important;
            max-width: 28rem !important;
            width: 100% !important;
            margin: auto;
            display: flex !important;
            flex-direction: column !important;
        }

        /* Form container with scroll if needed */
        #registrationForm {
            overflow-y: auto !important;
            flex-grow: 1 !important;
            max-height: calc(100vh - 8rem) !important;
        }

        /* Scrollbar styling for modal */
        #registrationModal .bg-white.rounded-xl {
            scrollbar-width: thin;
            scrollbar-color: #d1d5db #f3f4f6;
        }

        #registrationModal .bg-white.rounded-xl::-webkit-scrollbar {
            width: 6px;
        }

        #registrationModal .bg-white.rounded-xl::-webkit-scrollbar-track {
            background: #f3f4f6;
            border-radius: 3px;
        }

        #registrationModal .bg-white.rounded-xl::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 3px;
        }

        #registrationModal .bg-white.rounded-xl::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }

        /* Button area always visible at bottom */
        #registrationForm .flex.space-x-3 {
            position: sticky !important;
            bottom: 0 !important;
            background: white !important;
            padding: 1rem 0 0 0 !important;
            margin-top: auto !important;
            border-top: 1px solid #e5e7eb !important;
        }

        /* Mobile responsive adjustments */
        @media (max-width: 640px) {
            #registrationModal .flex.items-center.justify-center {
                padding: 0.5rem !important;
            }

            #registrationModal .bg-white.rounded-xl {
                max-height: calc(100vh - 1rem) !important;
                margin: 0.5rem !important;
                border-radius: 0.75rem !important;
            }

            #registrationForm {
                max-height: calc(100vh - 6rem) !important;
                padding: 1rem !important;
            }

            #registrationModal .p-6 {
                padding: 1rem !important;
            }
        }

        /* Landscape mode optimizations */
        @media (max-height: 600px) and (orientation: landscape) {
            #registrationModal .bg-white.rounded-xl {
                max-height: calc(100vh - 1rem) !important;
            }

            #registrationForm {
                max-height: calc(100vh - 4rem) !important;
            }

            #registrationModal .p-6 {
                padding: 0.75rem !important;
            }

            #registrationForm .space-y-4 > * {
                margin-bottom: 0.75rem !important;
            }
        }

        /* Touch scrolling for mobile */
        @media (max-width: 768px) {
            #registrationModal,
            #registrationModal .flex.items-center.justify-center,
            #registrationForm {
                -webkit-overflow-scrolling: touch !important;
            }
        }

        /* Ensure header stays fixed */
        #registrationModal .flex.items-center.justify-between.p-6 {
            flex-shrink: 0 !important;
            position: sticky !important;
            top: 0 !important;
            background: white !important;
            z-index: 10 !important;
            border-bottom: 1px solid #e5e7eb !important;
        }
    </style>
</head>
<body>
    <div class="min-h-screen">
        <!-- Hero Section -->
        <div class="hero-image h-80 relative">
            <?php
            // Construir URL de imagen correcta
            $imagenUrl = './assets/img/evento-default.jpg'; // Default
            if (!empty($evento['imagen'])) {
                if (filter_var($evento['imagen'], FILTER_VALIDATE_URL)) {
                    // Es una URL completa
                    $imagenUrl = $evento['imagen'];
                } else {
                    // Es un archivo local, usar endpoint del API
                    $imagenUrl = "./api/eventos.php?action=imagen&file=" . urlencode($evento['imagen']) . "&t=" . time();
                }
            }
            ?>
            <img src="<?= htmlspecialchars($imagenUrl) ?>"
                 alt="<?= htmlspecialchars($evento['titulo']) ?>"
                 class="w-full h-full object-cover"
                 onload="console.log('✅ Imagen cargada:', this.src)"
                 onerror="console.log('❌ Error cargando imagen:', this.src); this.style.display='none'">

            <!-- Floating badges -->
            <div class="absolute top-6 left-6 hero-content">
                <span class="badge-floating px-4 py-2 text-sm font-semibold rounded-full text-gray-800 inline-flex items-center">
                    <i class="fas fa-map-marker-alt mr-2 text-red-600"></i>
                    <?= htmlspecialchars($evento['modalidad'] ?: 'Presencial') ?>
                </span>
            </div>

            <div class="absolute top-6 right-6 hero-content">
                <span class="badge-floating px-4 py-2 text-sm font-bold rounded-full text-gray-800 inline-flex items-center">
                    <?php if ($evento['precio'] > 0): ?>
                        <i class="fas fa-tag mr-2 text-green-600"></i>
                        $<?= number_format($evento['precio'], 2) ?>
                    <?php else: ?>
                        <i class="fas fa-gift mr-2 text-green-600"></i>
                        Gratuito
                    <?php endif; ?>
                </span>
            </div>

            <!-- Estado del evento -->
            <div class="absolute bottom-6 left-6 hero-content">
                <span class="<?= $estadoCalculado === 'proximo' ? 'bg-green-500' : ($estadoCalculado === 'en_curso' ? 'bg-blue-500' : 'bg-gray-500') ?> text-white px-6 py-3 text-sm font-semibold rounded-full shadow-lg">
                    <i class="fas fa-clock mr-2"></i>
                    <?= $estadoCalculado === 'proximo' ? 'Próximo' : ($estadoCalculado === 'en_curso' ? 'En Curso' : 'Finalizado') ?>
                </span>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-5xl mx-auto -mt-20 relative z-10 px-6">
            <!-- Title Card -->
            <div class="info-card rounded-2xl p-8 mb-8 animate-in">
                <h1 class="text-4xl font-bold text-gray-900 mb-4 leading-tight"><?= htmlspecialchars($evento['titulo']) ?></h1>
                <p class="text-gray-600 text-lg leading-relaxed">
                    <?= nl2br(htmlspecialchars($evento['descripcion'] ?: 'No hay descripción disponible')) ?>
                </p>
            </div>

            <!-- Event Details Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Left Column - Event Info -->
                <div class="space-y-6">
                    <!-- Fecha y Hora -->
                    <div class="info-card rounded-xl p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center mr-4">
                                <i class="fas fa-calendar-alt text-red-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900 text-lg">Fecha y Hora</h3>
                                <p class="text-gray-500 text-sm">Cuándo se realizará</p>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <p class="text-gray-800 font-medium"><?= $fechaFormateada ?> a las <?= $horaFormateada ?></p>
                            <?php if ($fechaFinFormateada): ?>
                                <p class="text-gray-600 text-sm">Finaliza: <?= $fechaFinFormateada ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Ubicación -->
                    <div class="info-card rounded-xl p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mr-4">
                                <i class="fas fa-map-marker-alt text-blue-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900 text-lg">Ubicación</h3>
                                <p class="text-gray-500 text-sm">Dónde se realizará</p>
                            </div>
                        </div>
                        <div class="space-y-1">
                            <p class="text-gray-800 font-medium"><?= htmlspecialchars($evento['ubicacion'] ?: 'Por confirmar') ?></p>
                            <p class="text-gray-600 text-sm"><?= htmlspecialchars($evento['tipo'] ?: 'Evento') ?></p>

                            <?php if (!empty($evento['link_mapa'])): ?>
                            <div class="mt-3">
                                <a href="<?= htmlspecialchars($evento['link_mapa']) ?>" target="_blank"
                                   class="inline-flex items-center text-blue-600 hover:text-blue-700 text-sm font-medium transition">
                                    <i class="fas fa-external-link-alt mr-2"></i>
                                    Ver en mapa
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Stats & Actions -->
                <div class="space-y-6">
                    <!-- Capacidad -->
                    <div class="info-card rounded-xl p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center mr-4">
                                <i class="fas fa-users text-green-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900 text-lg">Capacidad</h3>
                                <p class="text-gray-500 text-sm">Asistentes registrados</p>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-2xl font-bold text-gray-900"><?= (int)($evento['capacidad_actual'] ?: 0) ?></span>
                                <span class="text-gray-600">de <?= (int)($evento['capacidad_maxima'] ?: 100) ?></span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <?php
                                $porcentaje = $evento['capacidad_maxima'] > 0 ?
                                    round(($evento['capacidad_actual'] / $evento['capacidad_maxima']) * 100, 1) : 0;
                                ?>
                                <div class="bg-gradient-to-r from-green-500 to-green-600 h-3 rounded-full transition-all duration-300" style="width: <?= min($porcentaje, 100) ?>%"></div>
                            </div>
                            <p class="text-sm text-gray-600"><?= $porcentaje ?>% ocupado</p>
                        </div>
                    </div>

                    <!-- Precio -->
                    <div class="info-card rounded-xl p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center mr-4">
                                <i class="fas fa-tag text-yellow-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900 text-lg">Precio</h3>
                                <p class="text-gray-500 text-sm">Costo de entrada</p>
                            </div>
                        </div>
                        <div class="text-3xl font-bold">
                            <?php if ($evento['precio'] > 0): ?>
                                <span class="text-gray-900">$<?= number_format($evento['precio'], 2) ?></span>
                            <?php else: ?>
                                <span class="text-green-600">Gratuito</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="space-y-4">
                        <button onclick="event.preventDefault(); event.stopPropagation(); handleEventRegistration(); return false;"
                                class="w-full btn-gradient text-white py-4 px-6 rounded-xl font-semibold text-lg flex items-center justify-center"
                                id="registerButton" type="button">
                            <i class="fas fa-user-plus mr-3"></i>
                            Registrarse al Evento
                        </button>
                        <div class="grid grid-cols-2 gap-4">
                            <button onclick="window.print()"
                                    class="border-2 border-gray-300 hover:border-red-600 text-gray-700 hover:text-red-600 py-3 px-4 rounded-xl font-semibold transition-all duration-200 flex items-center justify-center">
                                <i class="fas fa-print mr-2"></i>
                                Imprimir
                            </button>
                            <button onclick="navigator.share ? navigator.share({title: '<?= htmlspecialchars($evento['titulo']) ?>', text: '<?= htmlspecialchars(substr($evento['descripcion'], 0, 100)) ?>...', url: window.location.href}) : alert('Función de compartir no disponible')"
                                    class="border-2 border-gray-300 hover:border-blue-600 text-gray-700 hover:text-blue-600 py-3 px-4 rounded-xl font-semibold transition-all duration-200 flex items-center justify-center">
                                <i class="fas fa-share mr-2"></i>
                                Compartir
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información Adicional -->
        <?php if ($evento['organizador_id'] || $evento['comite_id']): ?>
        <div class="max-w-5xl mx-auto px-6 mb-8">
            <div class="info-card rounded-xl p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-info-circle mr-3 text-blue-600"></i>
                    Información Adicional
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-gray-600">
                    <?php if ($evento['organizador_id']): ?>
                        <div class="flex items-center">
                            <i class="fas fa-user-tie mr-2 text-gray-400"></i>
                            <span><strong>Organizador ID:</strong> <?= htmlspecialchars($evento['organizador_id']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($evento['comite_id']): ?>
                        <div class="flex items-center">
                            <i class="fas fa-users-cog mr-2 text-gray-400"></i>
                            <span><strong>Comité ID:</strong> <?= htmlspecialchars($evento['comite_id']) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="flex items-center">
                        <i class="fas fa-calendar-plus mr-2 text-gray-400"></i>
                        <span><strong>Creado:</strong> <?= date('d/m/Y H:i', strtotime($evento['fecha_creacion'])) ?></span>
                    </div>
                    <?php if ($evento['fecha_actualizacion'] !== $evento['fecha_creacion']): ?>
                        <div class="flex items-center">
                            <i class="fas fa-edit mr-2 text-gray-400"></i>
                            <span><strong>Actualizado:</strong> <?= date('d/m/Y H:i', strtotime($evento['fecha_actualizacion'])) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal de Registro -->
    <div id="registrationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50" style="backdrop-filter: blur(5px);">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl w-full max-w-md flex flex-col shadow-2xl">
                <div class="flex items-center justify-between p-6 border-b border-gray-200">
                    <h3 class="text-xl font-bold text-gray-800">Registrarse al Evento</h3>
                    <button onclick="closeRegistrationModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <form id="registrationForm" class="p-6 space-y-4">
                    <input type="hidden" value="<?= $eventoId ?>" id="evento_id">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre completo *</label>
                        <input type="text" id="nombre_usuario" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Correo electrónico *</label>
                        <input type="email" id="email_contacto" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                        <input type="tel" id="telefono_contacto"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Empresa</label>
                        <input type="text" id="nombre_empresa"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Comentarios</label>
                        <textarea id="comentarios" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500"></textarea>
                    </div>

                    <div class="flex space-x-3 pt-4">
                        <button type="button" onclick="closeRegistrationModal()"
                                class="flex-1 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                            Cancelar
                        </button>
                        <button type="submit" id="submitButton"
                                class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                            <i class="fas fa-user-plus mr-2"></i>Registrarse
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Notification Toast -->
    <div id="notification" class="fixed top-4 right-4 z-50 hidden">
        <div class="bg-white border-l-4 border-green-500 rounded-lg shadow-lg p-4 max-w-sm">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-500 text-xl"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-900" id="notificationMessage">
                        Registro exitoso
                    </p>
                </div>
                <div class="ml-auto pl-3">
                    <button onclick="hideNotification()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const eventoData = {
            id: <?= $eventoId ?>,
            titulo: <?= json_encode($evento['titulo']) ?>,
            fecha_inicio: <?= json_encode($evento['fecha_inicio']) ?>,
            capacidad_maxima: <?= $evento['capacidad_maxima'] ?>,
            capacidad_actual: <?= $evento['capacidad_actual'] ?>,
            // === NUEVOS CAMPOS ===
            link_evento: <?= json_encode($evento['link_evento'] ?? '') ?>,
            link_mapa: <?= json_encode($evento['link_mapa'] ?? '') ?>,
            tiene_beneficio: <?= $evento['tiene_beneficio'] ?? 0 ?>
        };

        // === LÓGICA CONDICIONAL DE BENEFICIOS ===
        function handleEventRegistration() {
            console.log('🚨🚨🚨 FUNCIÓN handleEventRegistration LLAMADA 🚨🚨🚨');
            console.log('🎯 Evaluando tipo de evento - tiene_beneficio:', eventoData.tiene_beneficio);
            console.log('🔍 Debug - link_evento:', eventoData.link_evento);
            console.log('🔍 Debug - link_mapa:', eventoData.link_mapa);

            if (eventoData.tiene_beneficio == 1) {
                // Evento CON beneficios: Mostrar formulario primero
                console.log('📝 Evento con beneficios: Mostrando formulario de registro');
                showRegistrationModal();
            } else {
                // Evento SIN beneficios: Ir directamente al link externo
                console.log('🔗 Evento sin beneficios: Redirigiendo directamente');

                // Verificar si el evento tiene link_evento configurado
                if (eventoData.link_evento && eventoData.link_evento.trim() !== '') {
                    console.log('🚀 Abriendo link inmediatamente en nueva pestaña:', eventoData.link_evento);
                    // Abrir inmediatamente en nueva pestaña
                    window.open(eventoData.link_evento, '_blank');
                    showNotification('Evento abierto en nueva pestaña', 'success', 2000);
                } else {
                    // Si no hay link_evento configurado, mostrar formulario de registro normal
                    console.log('⚠️ No hay link_evento configurado, mostrando formulario de registro');
                    showNotification('No hay enlace externo configurado. Mostrando formulario de registro.', 'info');
                    showRegistrationModal();
                }
            }
        }

        async function showRegistrationModal() {
            // Verificar si hay cupo disponible
            if (eventoData.capacidad_actual >= eventoData.capacidad_maxima) {
                showNotification('Cupo agotado. No hay espacios disponibles.', 'error');
                return;
            }

            // Verificar si el usuario ya está registrado (usando email predeterminado o del localStorage)
            const userEmail = localStorage.getItem('userEmail') || 'usuario@claut.mx';

            try {
                console.log('🔍 Verificando registro existente para:', userEmail);

                const checkResponse = await fetch(`./check_registro.php?evento_id=${eventoData.id}&email=${encodeURIComponent(userEmail)}`);
                const checkData = await checkResponse.json();

                console.log('📋 Resultado de verificación:', checkData);

                if (checkData.status === 'registered') {
                    // Usuario ya está registrado
                    const estado = checkData.data.estado_registro;
                    let mensaje = 'Ya te registraste a este evento.';

                    if (estado === 'pendiente') {
                        mensaje += ' Tu registro está pendiente de aprobación.';
                    } else if (estado === 'confirmado') {
                        mensaje += ' Tu registro ha sido confirmado.';
                    } else if (estado === 'rechazado') {
                        mensaje += ' Tu registro fue rechazado anteriormente.';
                    }

                    mensaje += `\nFecha de registro: ${checkData.data.fecha_registro}`;

                    showNotification(mensaje, 'info');
                    return;
                }

            } catch (error) {
                console.warn('⚠️ Error verificando registro, permitiendo continuar:', error);
                // En caso de error, permitir continuar con el registro
            }

            // Si no está registrado o hubo error, mostrar modal de registro
            document.getElementById('registrationModal').classList.remove('hidden');

            // Pre-llenar email si está disponible
            const emailInput = document.getElementById('email_contacto');
            if (emailInput && !emailInput.value) {
                emailInput.value = userEmail;
            }

            document.getElementById('nombre_usuario').focus();
        }

        function closeRegistrationModal() {
            document.getElementById('registrationModal').classList.add('hidden');
            document.getElementById('registrationForm').reset();
        }

        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            const messageElement = document.getElementById('notificationMessage');
            const iconElement = notification.querySelector('i');
            const borderElement = notification.querySelector('.bg-white');

            // Soporte para mensajes multi-línea
            if (message.includes('\n')) {
                messageElement.innerHTML = message.split('\n').map(line => `<p>${line}</p>`).join('');
            } else {
                messageElement.textContent = message;
            }

            // Configurar estilos según el tipo
            if (type === 'error') {
                iconElement.className = 'fas fa-exclamation-circle text-red-500 text-xl';
                borderElement.className = 'bg-white border-l-4 border-red-500 rounded-lg shadow-lg p-4 max-w-sm';
            } else if (type === 'info') {
                iconElement.className = 'fas fa-info-circle text-blue-500 text-xl';
                borderElement.className = 'bg-white border-l-4 border-blue-500 rounded-lg shadow-lg p-4 max-w-sm';
            } else if (type === 'warning') {
                iconElement.className = 'fas fa-exclamation-triangle text-yellow-500 text-xl';
                borderElement.className = 'bg-white border-l-4 border-yellow-500 rounded-lg shadow-lg p-4 max-w-sm';
            } else {
                iconElement.className = 'fas fa-check-circle text-green-500 text-xl';
                borderElement.className = 'bg-white border-l-4 border-green-500 rounded-lg shadow-lg p-4 max-w-sm';
            }

            notification.classList.remove('hidden');

            // Tiempo más largo para mensajes informativos
            const timeout = type === 'info' ? 8000 : 5000;
            setTimeout(() => {
                hideNotification();
            }, timeout);
        }

        function hideNotification() {
            document.getElementById('notification').classList.add('hidden');
        }

        document.getElementById('registrationForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const submitButton = document.getElementById('submitButton');
            const originalText = submitButton.innerHTML;

            // Mostrar spinner
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Registrando...';
            submitButton.disabled = true;

            try {
                const formData = {
                    evento_id: document.getElementById('evento_id').value,
                    nombre_usuario: document.getElementById('nombre_usuario').value,
                    email_contacto: document.getElementById('email_contacto').value,
                    telefono_contacto: document.getElementById('telefono_contacto').value,
                    nombre_empresa: document.getElementById('nombre_empresa').value,
                    comentarios: document.getElementById('comentarios').value
                };

                console.log('Enviando registro:', formData);

                const response = await fetch('./register_evento.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });

                const data = await response.json();
                console.log('Respuesta del servidor:', data);

                if (data.status === 'ok') {
                    // Guardar email del usuario en localStorage para futuras verificaciones
                    localStorage.setItem('userEmail', formData.email_contacto);
                    localStorage.setItem('userName', formData.nombre_usuario);

                    showNotification('¡Registro exitoso! Se ha enviado una notificación para aprobación.', 'success');
                    closeRegistrationModal();

                    // Actualizar capacidad local
                    eventoData.capacidad_actual++;

                    // === NUEVA LÓGICA: ABRIR LINK DEL EVENTO ===
                    if (eventoData.link_evento && eventoData.link_evento.trim() !== '') {
                        console.log('🔗 Abriendo link del evento:', eventoData.link_evento);
                        showNotification('Abriendo enlace del evento en nueva pestaña...', 'info', 3000);

                        setTimeout(() => {
                            window.open(eventoData.link_evento, '_blank');
                        }, 1500);
                    }

                    // Opcional: recargar la página después de un delay mayor
                    setTimeout(() => {
                        window.location.reload();
                    }, 4000);

                } else if (data.status === 'exists') {
                    showNotification('Ya estás registrado a este evento.', 'error');
                } else if (data.status === 'full') {
                    showNotification('Cupo agotado. No hay espacios disponibles.', 'error');
                } else {
                    showNotification(data.message || 'Error al registrarse. Inténtalo nuevamente.', 'error');
                }

            } catch (error) {
                console.error('Error:', error);
                showNotification('Error de conexión. Inténtalo nuevamente.', 'error');
            } finally {
                // Restaurar botón
                submitButton.innerHTML = originalText;
                submitButton.disabled = false;
            }
        });

        // Prellenar formulario con datos guardados si existen
        function preFillUserData() {
            const savedEmail = localStorage.getItem('userEmail');
            const savedName = localStorage.getItem('userName');

            if (savedEmail) {
                const emailInput = document.getElementById('email_contacto');
                if (emailInput) emailInput.value = savedEmail;
            }

            if (savedName) {
                const nameInput = document.getElementById('nombre_usuario');
                if (nameInput) nameInput.value = savedName;
            }
        }

        // Cargar datos del usuario al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            preFillUserData();
        });

        // Cerrar modal con Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeRegistrationModal();
                hideNotification();
            }
        });

        // Cerrar modal clickeando fuera
        document.getElementById('registrationModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRegistrationModal();
            }
        });
    </script>
</body>
</html>