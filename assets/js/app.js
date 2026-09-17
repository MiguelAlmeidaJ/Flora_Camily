document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[name="customer_phone"]').forEach((input) => {
        input.addEventListener('input', (event) => {
            let value = event.target.value.replace(/\D/g, '').slice(0, 11);
            if (value.length > 10) {
                value = value.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
            } else if (value.length > 6) {
                value = value.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
            } else if (value.length > 2) {
                value = value.replace(/^(\d{2})(\d{0,5}).*/, '($1) $2');
            } else if (value.length) {
                value = value.replace(/^(\d{0,2})/, '($1');
            }
            event.target.value = value;
        });
    });
});
