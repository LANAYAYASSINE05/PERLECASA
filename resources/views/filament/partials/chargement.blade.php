<div id="pc-chargement-mini" class="pc-progression" aria-hidden="true"></div>
<div id="pc-chargement" class="pc-chargement" role="status" aria-live="polite">
    <div class="pc-chargement-fond">
        <div class="pc-chargement-scene">
            <span class="pc-chargement-logo" style="--pc-embleme: url('{{ asset('images/embleme.png') }}')">
                <img src="{{ asset('images/embleme.png') }}" alt="" width="330" height="226">
                <span class="pc-chargement-reflet"></span>
            </span>
            <span class="pc-chargement-nom">Perle Casa</span>
            <span class="pc-chargement-sous">Immobilier</span>
            <span class="pc-chargement-barre"><span></span></span>
            <span class="sr-only">Chargement…</span>
        </div>
    </div>
</div>
<script>
    /*
     * Première page de la session : écran complet au logo animé.
     * Pages suivantes : seulement la barre dorée (#pc-chargement-mini), reprise ensuite par application.js.
     */
    (function () {
        var ecran = document.getElementById('pc-chargement');
        var mini = document.getElementById('pc-chargement-mini');
        var calme = matchMedia('(prefers-reduced-motion: reduce)').matches;
        var intro = false;

        try {
            intro = !calme && !sessionStorage.getItem('pc-intro-vue');
            sessionStorage.setItem('pc-intro-vue', '1');
        } catch (e) {}

        if (intro) {
            ecran.classList.add('pc-chargement-intro');
        } else {
            ecran.remove();
            mini.classList.add('pc-progression-reprise', 'pc-progression-active');
        }

        var debut = performance.now();
        var dureeMini = intro ? 1500 : 0;

        window.pcChargementFini = new Promise(function (fin) {
            function cacher() {
                setTimeout(function () {
                    fin();

                    if (intro) {
                        ecran.classList.add('pc-chargement-fini');
                        setTimeout(function () { ecran.remove(); }, 650);
                    } else {
                        mini.classList.replace('pc-progression-active', 'pc-progression-fin');
                    }
                }, Math.max(0, dureeMini - (performance.now() - debut)));
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', cacher);
            } else {
                cacher();
            }
        });
    })();
</script>
