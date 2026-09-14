document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.dma-wrapper').forEach(function (wrapper) {
        var abrirBtn     = wrapper.querySelector('.dma-btn-abrir');
        var modal        = wrapper.querySelector('.dma-modal');
        var cancelarBtn  = wrapper.querySelector('.dma-btn-cancelar');
        var confirmarBtn = wrapper.querySelector('.dma-btn-confirmar');
        var passwordInp  = wrapper.querySelector('.dma-input-password');
        var errorP       = wrapper.querySelector('.dma-error');

        if (!abrirBtn || !modal || !confirmarBtn) return;

        function mostrarError(msg) {
            if (!errorP) return;
            errorP.textContent = msg;
            errorP.hidden = false;
        }

        function ocultarError() {
            if (!errorP) return;
            errorP.hidden = true;
            errorP.textContent = '';
        }

        function abrirModal() {
            ocultarError();
            if (passwordInp) passwordInp.value = '';
            modal.hidden = false;
            if (passwordInp) passwordInp.focus();
        }

        function cerrarModal() {
            modal.hidden = true;
        }

        abrirBtn.addEventListener('click', abrirModal);

        if (cancelarBtn) {
            cancelarBtn.addEventListener('click', cerrarModal);
        }

        modal.addEventListener('click', function (e) {
            if (e.target === modal) cerrarModal();
        });

        confirmarBtn.addEventListener('click', function () {
            ocultarError();

            if (passwordInp && passwordInp.value.trim() === '') {
                mostrarError('Please enter your password to confirm.');
                return;
            }

            confirmarBtn.disabled = true;
            confirmarBtn.textContent = '...';

            var formData = new FormData();
            formData.append('action', 'dma_eliminar_cuenta');
            formData.append('nonce', abrirBtn.getAttribute('data-nonce'));
            if (passwordInp) {
                formData.append('password', passwordInp.value);
            }

            fetch(dmaAjax.url, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    window.location.href = data.data.redirect || '/';
                } else {
                    mostrarError((data.data && data.data.message) || 'Something went wrong.');
                    confirmarBtn.disabled = false;
                    confirmarBtn.textContent = confirmarBtn.dataset.textoOriginal || 'Confirm';
                }
            })
            .catch(function () {
                mostrarError('Network error. Please try again.');
                confirmarBtn.disabled = false;
            });
        });

        // Remember the original confirm label for re-enabling after an error.
        confirmarBtn.dataset.textoOriginal = confirmarBtn.textContent;
    });
});
