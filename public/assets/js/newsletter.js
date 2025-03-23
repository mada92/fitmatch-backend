/**
 * Obsługa formularza zapisu do newslettera
 * Ten skrypt zastępuje standardową obsługę formularza w main.js
 */
document.addEventListener('DOMContentLoaded', function() {
    const newsletterForm = document.getElementById('newsletter-form');
    const successMessage = document.getElementById('subscribe-success');
    
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Pobierz adres e-mail
            const emailInput = this.querySelector('input[type="email"]');
            const email = emailInput.value.trim();

            if (!email) {
                showError('Proszę podać adres e-mail.');
                return;
            }
            
            // Ukryj ewentualny poprzedni komunikat
            if (successMessage) {
                successMessage.style.display = 'none';
            }
            
            // Wyłącz przycisk na czas wysyłania
            const submitButton = this.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = 'Przetwarzanie...';
            }
            
            // Wyślij zapytanie AJAX
            fetch('/newsletter/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ email: email })
            })
            .then(response => response.json())
            .then(data => {
                // Przywróć stan przycisku
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = 'Zapisz się do newslettera!';
                }
                
                if (data.success) {
                    // Pokaż komunikat sukcesu
                    if (successMessage) {
                        successMessage.textContent = data.message;
                        successMessage.style.display = 'block';
                    }
                    
                    // Wyczyść formularz
                    newsletterForm.reset();
                    
                    // Ukryj komunikat po czasie
                    setTimeout(function() {
                        if (successMessage) {
                            successMessage.style.display = 'none';
                        }
                    }, 5000);
                } else {
                    showError(data.message || 'Wystąpił błąd. Spróbuj ponownie później.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                
                // Przywróć stan przycisku
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = 'Zapisz się do newslettera!';
                }
                
                showError('Wystąpił błąd podczas łączenia z serwerem. Spróbuj ponownie później.');
            });
        });
    }
    
    // Funkcja wyświetlająca błędy
    function showError(message) {
        // Jeśli istnieje element do wyświetlania błędów - użyj go
        let errorElement = document.getElementById('subscribe-error');
        
        // Jeśli elementu nie ma, utwórz go
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.id = 'subscribe-error';
            errorElement.className = 'error-message';
            errorElement.style.display = 'none';
            
            // Wstaw element po formularzu, ale przed div.success-message jeśli istnieje
            if (newsletterForm) {
                if (successMessage) {
                    newsletterForm.parentNode.insertBefore(errorElement, successMessage);
                } else {
                    newsletterForm.parentNode.insertBefore(errorElement, newsletterForm.nextSibling);
                }
            }
        }
        
        errorElement.textContent = message;
        errorElement.style.display = 'block';
        
        setTimeout(function() {
            errorElement.style.display = 'none';
        }, 5000);
    }
});
