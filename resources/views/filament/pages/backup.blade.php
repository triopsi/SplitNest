<x-filament-panels::page>
    <div class="hh-stack">
        <section class="hh-section">
            <div class="hh-section-head"><h3>Backup &amp; Umzug</h3></div>
            <p class="hh-note">Der Export lädt <b>Teilnehmer, Einkünfte, Kosten und Monatsabschlüsse</b> als ZIP-Datei herunter. Bewahre die Datei als Sicherung auf oder spiele sie in einer anderen SplitNest-Instanz wieder ein — dort ersetzt sie den kompletten Datenbestand.</p>
            <p class="hh-note">Benutzerkonten werden aus Sicherheitsgründen nicht übertragen. Logins legst du in der Zielinstanz mit <code>php artisan splitnest:user</code> an.</p>
            <div class="hh-note" style="display:flex;gap:.75rem;flex-wrap:wrap">{{ $this->exportAction }}{{ $this->importAction }}</div>
        </section>
    </div>
</x-filament-panels::page>
