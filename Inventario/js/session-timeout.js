let inactivityTimeout;
const timeoutDuration = 10 * 60 * 1000; 

function resetInactivityTimer() {
    clearTimeout(inactivityTimeout);
    inactivityTimeout = setTimeout(logoutUser, timeoutDuration);
}

function logoutUser() {
    
    const xhr = new XMLHttpRequest();
    xhr.open('GET', 'logout.php', true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onload = function() {
        if (xhr.status === 200) {
            window.location.href = '../../index.php';
        }
    };
    xhr.send();
}


document.addEventListener('mousemove', resetInactivityTimer);
document.addEventListener('keypress', resetInactivityTimer);
document.addEventListener('click', resetInactivityTimer);
document.addEventListener('scroll', resetInactivityTimer);


document.addEventListener('DOMContentLoaded', resetInactivityTimer);