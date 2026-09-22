/**
 * Configuración específica del módulo de boletines
 * Cluster Intranet v1.0
 */

window.BoletinesConfig = {
    // URLs de la API
    apiEndpoints: {
        boletines: './api/boletines_simple.php',
        archivos: './api/boletines_archivos.php',
        upload: './api/upload.php'
    },
    
    // Configuración de visualización
    display: {
        itemsPerPage: 12,
        defaultView: 'grid', // 'grid' o 'list'
        autoRefreshInterval: 300000, // 5 minutos
        showPreview: true,
        enableSearch: true,
        enableFilters: true
    },
    
    // Tipos de archivo soportados
    supportedFileTypes: {
        images: ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
        documents: ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'],
        text: ['txt', 'rtf', 'csv'],
        video: ['mp4', 'webm', 'ogg'],
        audio: ['mp3', 'wav', 'ogg']
    },
    
    // Configuración de modal
    modal: {
        maxWidth: '90vw',
        maxHeight: '90vh',
        showDownloadButton: true,
        enableFullscreen: true,
        previewTimeout: 30000 // 30 segundos
    },
    
    // Textos de la interfaz
    messages: {
        loading: 'Cargando boletines desde la base de datos...',
        noResults: 'No hay boletines disponibles',
        error: 'Error al cargar los boletines',
        searchPlaceholder: 'Buscar en boletines...',
        downloadSuccess: 'Descarga iniciada',
        viewerError: 'Error al cargar el documento'
    },
    
    // Configuración de formato
    dateFormat: {
        locale: 'es-ES',
        options: {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        }
    },
    
    // Estados permitidos
    allowedStates: ['publicado', 'borrador', 'archivado'],
    
    // Configuración de cache
    cache: {
        enabled: true,
        duration: 300000, // 5 minutos
        maxItems: 100
    },
    
    // Configuración de analytics
    analytics: {
        trackViews: true,
        trackDownloads: true,
        trackSearches: false
    },
    
    // Configuración de accesibilidad
    accessibility: {
        enableKeyboardNavigation: true,
        announceChanges: true,
        highContrastMode: false,
        fontSize: 'normal' // 'small', 'normal', 'large'
    },
    
    // Configuración de desarrollo
    debug: {
        enabled: false,
        logLevel: 'info', // 'debug', 'info', 'warn', 'error'
        showPerformanceMetrics: false
    }
};

// Configuración específica por entorno
if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
    // Configuración para desarrollo
    window.BoletinesConfig.debug.enabled = true;
    window.BoletinesConfig.debug.showPerformanceMetrics = true;
    window.BoletinesConfig.cache.enabled = false;
}
