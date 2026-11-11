let inactivityTimeout;
const timeoutDuration = 10 * 60 * 1000; 

function resetInactivityTimer() {
    clearTimeout(inactivityTimeout);
    inactivityTimeout = setTimeout(logoutUser, timeoutDuration);
}

function logoutUser() {
    // Obtener la ruta base del proyecto
    const origin = window.location.origin;
    const pathArray = window.location.pathname.split('/').filter(p => p !== '');
    const almacenIndex = pathArray.indexOf('almacen');
    
    // Construir las rutas
    let logoutPath;
    let redirectPath;
    
    if (almacenIndex === -1) {
        // Si no se encuentra 'almacen' en la ruta, usar rutas por defecto
        logoutPath = origin + '/almacen/logout.php';
        redirectPath = origin + '/index.php';
    } else if (almacenIndex === 0) {
        // Si 'almacen' está en la raíz del servidor (ej: /almacen/...)
        logoutPath = origin + '/almacen/logout.php';
        redirectPath = origin + '/index.php';
    } else {
        // Si el proyecto está en un subdirectorio (ej: /gk/almacen/...)
        const basePath = '/' + pathArray.slice(0, almacenIndex + 1).join('/');
        const projectBase = '/' + pathArray.slice(0, almacenIndex).join('/');
        logoutPath = origin + basePath + '/logout.php';
        redirectPath = origin + projectBase + '/index.php';
    }
    
    const xhr = new XMLHttpRequest();
    xhr.open('GET', logoutPath, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onload = function() {
        // Redirigir siempre, independientemente de la respuesta
        window.location.href = redirectPath;
    };
    xhr.onerror = function() {
        // Si hay error, redirigir de todas formas
        window.location.href = redirectPath;
    };
    xhr.send();
}

document.addEventListener('mousemove', resetInactivityTimer);
document.addEventListener('keypress', resetInactivityTimer);
document.addEventListener('click', resetInactivityTimer);
document.addEventListener('scroll', resetInactivityTimer);
document.addEventListener('DOMContentLoaded', resetInactivityTimer);

