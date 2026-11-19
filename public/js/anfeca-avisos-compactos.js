
(() => {
    const CLASSES = [
        "alert-danger",
        "alert-success",
        "alert-warning",
        "alert-info"
    ];

    const TYPES = {
        "alert-danger": "anfeca-aviso-error",
        "alert-success": "anfeca-aviso-exito",
        "alert-warning": "anfeca-aviso-advertencia",
        "alert-info": "anfeca-aviso-info"
    };

    function compactarAvisos() {
        document.querySelectorAll(
            ".alert-danger, .alert-success, .alert-warning, .alert-info"
        ).forEach(elemento => {
            elemento.classList.add("anfeca-aviso");

            for (const clase of CLASSES) {
                if (elemento.classList.contains(clase)) {
                    elemento.classList.add(TYPES[clase]);
                    break;
                }
            }
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener(
            "DOMContentLoaded",
            compactarAvisos
        );
    } else {
        compactarAvisos();
    }

    const observer = new MutationObserver(compactarAvisos);

    observer.observe(document.documentElement, {
        childList: true,
        subtree: true
    });
})();
