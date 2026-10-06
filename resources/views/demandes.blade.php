<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Suivi des demandes d'actes</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --fond: #f4f6f9;
            --surface: #ffffff;
            --texte: #1f2933;
            --discret: #616e7c;
            --bordure: #e4e7eb;
            --primaire: #0b6e4f;
            --primaire-fonce: #08563e;
            --deposee: #2563eb;   --deposee-fond: #e3ecff;
            --en_cours: #b45309;  --en_cours-fond: #fff1d6;
            --validee: #047857;   --validee-fond: #d5f5e7;
            --rejetee: #b42318;   --rejetee-fond: #fde4e1;
            --rayon: 12px;
            --ombre: 0 1px 2px rgba(16, 24, 40, .06), 0 1px 3px rgba(16, 24, 40, .08);
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--fond); color: var(--texte); font: 15px/1.5 'Montserrat', system-ui, sans-serif; }
        button, input, select, textarea { font: inherit; }
        .conteneur { max-width: 1120px; margin: 0 auto; padding: 0 16px; }

        /* En-tête */
        .entete { background: linear-gradient(120deg, var(--primaire-fonce), var(--primaire)); color: #fff; padding: 28px 0 72px; }
        .entete h1 { margin: 0; font-size: 1.6rem; font-weight: 700; letter-spacing: -.01em; }
        .entete p { margin: 6px 0 0; opacity: .85; font-size: .95rem; }
        main.conteneur { margin-top: -48px; padding-bottom: 48px; }

        /* Cartes */
        .carte { background: var(--surface); border-radius: var(--rayon); box-shadow: var(--ombre); padding: 20px; margin-bottom: 20px; }
        .carte-entete { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: 8px; margin-bottom: 12px; }
        .carte-entete h2 { margin: 0; font-size: 1.1rem; font-weight: 600; }
        .discret { color: var(--discret); font-size: .875rem; }

        /* Recherche */
        #recherche { display: grid; grid-template-columns: minmax(180px, 2fr) minmax(160px, 1.5fr) minmax(110px, 1fr) auto; gap: 12px; align-items: end; }
        .champ { display: flex; flex-direction: column; gap: 6px; min-width: 0; font-size: .8rem; font-weight: 600; color: var(--discret); text-transform: uppercase; letter-spacing: .04em; }
        .champ input, .champ select, .champ textarea { width: 100%; padding: 10px 12px; border: 1px solid var(--bordure); border-radius: 8px; font-size: .95rem; color: var(--texte); background: #fff; text-transform: none; letter-spacing: normal; font-weight: 500; }
        .champ input:focus, .champ select:focus, .champ textarea:focus { outline: 2px solid var(--primaire); outline-offset: 1px; border-color: transparent; }
        .demo { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 14px; font-size: .85rem; color: var(--discret); }
        .puce { border: 1px solid var(--bordure); background: var(--fond); color: var(--texte); border-radius: 999px; padding: 4px 12px; cursor: pointer; font-size: .8rem; font-weight: 500; }
        .puce:hover { border-color: var(--primaire); color: var(--primaire); }

        /* Boutons */
        .bouton { border: none; border-radius: 8px; padding: 10px 18px; font-weight: 600; cursor: pointer; transition: background .15s; }
        .bouton:disabled { opacity: .45; cursor: default; }
        .principal { background: var(--primaire); color: #fff; }
        .principal:hover:not(:disabled) { background: var(--primaire-fonce); }
        .secondaire { background: var(--fond); color: var(--texte); border: 1px solid var(--bordure); }
        .secondaire:hover:not(:disabled) { border-color: var(--discret); }
        .danger { background: var(--rejetee); color: #fff; }
        .action { padding: 5px 10px; font-size: .78rem; margin: 2px 4px 2px 0; }
        .action.en_cours { background: var(--deposee-fond); color: var(--deposee); }
        .action.validee { background: var(--validee-fond); color: var(--validee); }
        .action.rejetee { background: var(--rejetee-fond); color: var(--rejetee); }
        .action:hover { filter: brightness(.95); }

        /* Statistiques : cartes cliquables qui filtrent la liste */
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 14px; margin-bottom: 20px; }
        .stat { position: relative; display: flex; flex-direction: column; min-height: 132px; text-align: left; background: var(--surface); border: 2px solid transparent; border-radius: var(--rayon); box-shadow: var(--ombre); padding: 16px 16px 18px; cursor: pointer; overflow: hidden; transition: transform .15s, border-color .15s; }
        .stat:hover { transform: translateY(-2px); }
        .stat[aria-pressed="true"] { border-color: var(--couleur, var(--primaire)); }
        .stat::before { content: ''; position: absolute; inset: 0 auto 0 0; width: 4px; background: var(--couleur, var(--primaire)); }
        .stat-libelle { display: block; padding-right: 44px; font-size: .8rem; font-weight: 600; color: var(--discret); text-transform: uppercase; letter-spacing: .04em; }
        /* margin-top: auto aligne chiffres et barres en bas des cartes, même si un libellé passe sur deux lignes. */
        .stat-nombre { display: block; font-size: 2rem; font-weight: 700; line-height: 1.2; margin: auto 0 10px; padding-top: 6px; font-variant-numeric: tabular-nums; }
        .stat-barre { display: block; height: 6px; border-radius: 3px; background: var(--fond); overflow: hidden; }
        .stat-barre > span { display: block; height: 100%; border-radius: 3px; background: var(--couleur, var(--primaire)); transition: width .4s ease; }
        .stat-part { position: absolute; top: 16px; right: 16px; font-size: .78rem; font-weight: 600; color: var(--couleur, var(--primaire)); }

        /* Tableau */
        .table-wrap { overflow-x: auto; margin: 0 -20px; padding: 0 20px; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th { text-align: left; font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--discret); padding: 10px; border-bottom: 1px solid var(--bordure); white-space: nowrap; }
        /* Cellules sur une ligne : sur petit écran, le tableau défile horizontalement au lieu de s'étirer en hauteur. */
        td { padding: 12px 10px; border-bottom: 1px solid var(--bordure); vertical-align: middle; white-space: nowrap; }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: #fafbfc; }
        .num { font-variant-numeric: tabular-nums; color: var(--discret); }
        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px; font-size: .78rem; font-weight: 600; white-space: nowrap; color: var(--couleur); background: var(--couleur-fond); }
        .badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .motif { color: var(--rejetee); font-size: .85rem; white-space: normal; min-width: 140px; }
        .vide { text-align: center; padding: 40px 12px; color: var(--discret); }

        .deposee { --couleur: var(--deposee); --couleur-fond: var(--deposee-fond); }
        .en_cours { --couleur: var(--en_cours); --couleur-fond: var(--en_cours-fond); }
        .validee { --couleur: var(--validee); --couleur-fond: var(--validee-fond); }
        .rejetee { --couleur: var(--rejetee); --couleur-fond: var(--rejetee-fond); }

        .pagination { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; margin-top: 16px; }
        .pagination .boutons { display: flex; gap: 8px; }

        /* Skeleton loader */
        .squelette { display: block; height: 12px; border-radius: 6px; background: linear-gradient(90deg, #eceff3 25%, #f6f8fa 37%, #eceff3 63%); background-size: 400% 100%; animation: reflet 1.4s ease infinite; }
        .squelette.court { width: 40%; }
        .squelette.moyen { width: 65%; }
        .squelette.grand { height: 30px; width: 45%; margin: 10px 0 12px; }
        .stat.chargement { cursor: default; }
        .stat.chargement::before { background: var(--bordure); }
        @keyframes reflet { 0% { background-position: 100% 50%; } 100% { background-position: 0 50%; } }
        @media (prefers-reduced-motion: reduce) { .squelette { animation: none; } .stat, .stat-barre > span { transition: none; } }

        /* Alerte, notification et dialogue */
        .alerte { border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; background: var(--rejetee-fond); color: var(--rejetee); font-weight: 500; }
        .toast { position: fixed; right: 16px; bottom: 16px; max-width: min(420px, calc(100vw - 32px)); padding: 12px 16px; border-radius: 8px; background: var(--texte); color: #fff; box-shadow: 0 8px 24px rgba(0, 0, 0, .18); font-size: .9rem; opacity: 0; transform: translateY(8px); transition: opacity .2s, transform .2s; pointer-events: none; }
        .toast.visible { opacity: 1; transform: none; }
        .toast.erreur { background: var(--rejetee); }
        dialog { border: none; border-radius: var(--rayon); padding: 24px; width: min(460px, calc(100vw - 32px)); box-shadow: 0 20px 48px rgba(0, 0, 0, .2); }
        dialog::backdrop { background: rgba(16, 24, 40, .45); }
        dialog h3 { margin: 0 0 4px; font-size: 1.1rem; }
        dialog .champ { margin-top: 16px; }
        dialog textarea { resize: vertical; }
        .erreur-champ { color: var(--rejetee); font-size: .85rem; margin: 8px 0 0; }
        .actions-dialogue { display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px; }

        @media (max-width: 720px) {
            #recherche { grid-template-columns: 1fr 1fr; }
            #recherche .champ:first-child, #recherche .bouton { grid-column: 1 / -1; }
            .entete h1 { font-size: 1.3rem; }
        }
    </style>
</head>
<body>
<header class="entete">
    <div class="conteneur">
        <h1>Suivi des demandes d'actes</h1>
        <p>Acte de naissance, casier judiciaire et certificat de résidence : consultez et traitez les demandes d'un usager.</p>
    </div>
</header>

<main class="conteneur">
    <section class="carte" aria-label="Recherche">
        <form id="recherche">
            <label class="champ">NPI de l'usager
                <input id="npi" placeholder="10 chiffres" maxlength="10" inputmode="numeric" value="1234567890" required>
            </label>
            <label class="champ">Statut
                <select id="statut">
                    <option value="">Tous les statuts</option>
                    @foreach (\App\Enums\StatutDemande::cases() as $statut)
                        <option value="{{ $statut->value }}">{{ $statut->libelle() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="champ">Par page
                <select id="par-page">
                    <option value="5">5</option>
                    <option value="10">10</option>
                    <option value="20" selected>20 (max.)</option>
                </select>
            </label>
            <button type="submit" class="bouton principal">Afficher</button>
        </form>
        <div class="demo">
            Données de démonstration :
            <button type="button" class="puce" data-npi="1234567890">1234567890 · tous les statuts</button>
            <button type="button" class="puce" data-npi="1111111111">1111111111 · 25 demandes</button>
        </div>
    </section>

    <div id="alerte" class="alerte" role="alert" hidden></div>

    <section id="stats" class="stats" aria-label="Nombre de demandes par statut"></section>

    <section class="carte">
        <div class="carte-entete">
            <h2 id="titre-liste">Demandes</h2>
            <span id="info-page" class="discret"></span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>N°</th><th>Type d'acte</th><th>Copies</th><th>Statut</th><th>Motif de rejet</th><th>Déposée le</th><th>Actions</th></tr>
                </thead>
                <tbody id="lignes"></tbody>
            </table>
        </div>
        <div class="pagination">
            <span id="resume" class="discret"></span>
            <div class="boutons">
                <button id="precedent" class="bouton secondaire" disabled>← Précédent</button>
                <button id="suivant" class="bouton secondaire" disabled>Suivant →</button>
            </div>
        </div>
    </section>
</main>

<dialog id="dialogue-rejet" aria-labelledby="titre-rejet">
    <form id="form-rejet">
        <h3 id="titre-rejet">Rejeter la demande n° <span id="rejet-id"></span></h3>
        <p class="discret">Un rejet doit toujours être motivé.</p>
        <label class="champ">Motif du rejet
            <textarea id="rejet-motif" rows="3" maxlength="1000"></textarea>
        </label>
        <p id="rejet-erreur" class="erreur-champ" hidden></p>
        <div class="actions-dialogue">
            <button type="button" id="rejet-annuler" class="bouton secondaire">Annuler</button>
            <button type="submit" class="bouton danger">Confirmer le rejet</button>
        </div>
    </form>
</dialog>

<div id="toast" class="toast" role="status" aria-live="polite"></div>

@php
    // Libellés issus des enums PHP : une seule source de vérité pour l'écran et l'API.
    $libellesStatut = collect(\App\Enums\StatutDemande::cases())->mapWithKeys(fn ($s) => [$s->value => $s->libelle()]);
@endphp
<script>
    const LIBELLES_STATUT = @json($libellesStatut);
</script>

@verbatim
<script>
    // Écran en JavaScript natif : il consomme l'API REST, sans étape de build.
    const LIBELLES_ACTION = { en_cours: 'Prendre en charge', validee: 'Valider', rejetee: 'Rejeter' };
    const FORMAT_DATE = new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    let page = 1;
    let demandeARejeter = null;

    const el = (id) => document.getElementById(id);
    const echapper = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    /** Appelle l'API en JSON et renvoie { ok, json }. */
    async function appelerApi(url, options = {}) {
        const rep = await fetch(url, {
            ...options,
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        });
        return { ok: rep.ok, json: await rep.json() };
    }

    /** Message clair renvoyé par l'API, avec le détail par champ pour une erreur 422. */
    function messageErreur(json) {
        const details = json.errors ? Object.values(json.errors).flat().join(' ') : '';
        return `${json.message} ${details}`.trim();
    }

    function afficherAlerte(texte) {
        el('alerte').textContent = texte;
        el('alerte').hidden = !texte;
    }

    let minuterieToast;
    function afficherToast(texte, erreur = false) {
        const toast = el('toast');
        toast.textContent = texte;
        toast.className = `toast visible${erreur ? ' erreur' : ''}`;
        clearTimeout(minuterieToast);
        minuterieToast = setTimeout(() => toast.classList.remove('visible'), 4000);
    }

    // ---------- Skeleton loader ----------

    function squeletteStats() {
        el('stats').innerHTML = Array(5).fill(`
            <div class="stat chargement" aria-hidden="true">
                <span class="squelette court"></span>
                <span class="squelette grand"></span>
                <span class="squelette"></span>
            </div>`).join('');
    }

    function squeletteLignes() {
        const nb = Math.min(Number(el('par-page').value), 6);
        const cellules = ['court', 'moyen', 'court', 'moyen', 'moyen', 'moyen', 'court']
            .map((l) => `<td><span class="squelette ${l}"></span></td>`).join('');
        el('lignes').innerHTML = Array(nb).fill(`<tr aria-hidden="true">${cellules}</tr>`).join('');
        el('lignes').setAttribute('aria-busy', 'true');
    }

    // ---------- Chargement ----------

    /**
     * Charge la liste (et, si demandé, les statistiques) de l'usager saisi.
     * Les statistiques ne dépendent ni de la page ni du filtre : inutile de les recharger en paginant.
     */
    async function charger({ avecStats = true } = {}) {
        const npi = el('npi').value.trim();
        const statut = el('statut').value;
        afficherAlerte('');
        squeletteLignes();
        if (avecStats) squeletteStats();
        el('titre-liste').textContent = `Demandes de l'usager ${npi}`;
        el('info-page').textContent = el('resume').textContent = '';
        el('precedent').disabled = el('suivant').disabled = true;

        const params = new URLSearchParams({ page, par_page: el('par-page').value });
        if (statut) params.set('statut', statut);

        try {
            const [liste, stats] = await Promise.all([
                appelerApi(`/api/usagers/${encodeURIComponent(npi)}/demandes?${params}`),
                avecStats ? appelerApi(`/api/statistiques?npi=${encodeURIComponent(npi)}`) : null,
            ]);

            if (!liste.ok) {
                afficherAlerte(messageErreur(liste.json));
                el('stats').innerHTML = '';
                afficherVide('Corrigez le NPI pour afficher les demandes.');
                afficherPagination(null);
                return;
            }

            afficherLignes(liste.json.data, statut);
            afficherPagination(liste.json.meta);
            if (stats?.ok) afficherStats(stats.json.data);
        } catch (err) {
            afficherAlerte("Impossible de joindre l'API. Vérifiez que le serveur est démarré (php artisan serve).");
            el('stats').innerHTML = '';
            afficherVide('Aucune donnée.');
            afficherPagination(null);
        } finally {
            el('lignes').removeAttribute('aria-busy');
            marquerStatActive();
        }
    }

    function afficherVide(texte) {
        el('lignes').innerHTML = `<tr><td colspan="7" class="vide">${echapper(texte)}</td></tr>`;
    }

    function afficherLignes(demandes, statut) {
        if (demandes.length === 0) {
            afficherVide(statut
                ? `Aucune demande « ${LIBELLES_STATUT[statut]} » pour cet usager.`
                : 'Aucune demande pour cet usager.');
            return;
        }

        el('lignes').innerHTML = demandes.map((d) => `
            <tr>
                <td class="num">#${d.id}</td>
                <td><strong>${echapper(d.type_acte_libelle)}</strong></td>
                <td class="num">${d.nombre_copies}</td>
                <td><span class="badge ${d.statut}">${echapper(d.statut_libelle)}</span></td>
                <td class="motif">${echapper(d.motif_rejet ?? '')}</td>
                <td class="num">${FORMAT_DATE.format(new Date(d.cree_le))}</td>
                <td>${d.transitions_possibles.map((cible) => `
                    <button class="bouton action ${cible}" data-id="${d.id}" data-cible="${cible}">${LIBELLES_ACTION[cible]}</button>`).join('')
                    || '<span class="discret">—</span>'}
                </td>
            </tr>`).join('');
    }

    function afficherPagination(meta) {
        el('info-page').textContent = meta ? `${meta.total} demande(s)` : '';
        el('resume').textContent = meta && meta.total > 0
            ? `Page ${meta.current_page} sur ${meta.last_page} · demandes ${meta.from} à ${meta.to}`
            : '';
        el('precedent').disabled = !meta || meta.current_page <= 1;
        el('suivant').disabled = !meta || meta.current_page >= meta.last_page;
    }

    function afficherStats({ total, par_statut }) {
        const carte = (statut, libelle, nombre) => {
            const part = total > 0 ? Math.round((nombre / total) * 100) : 0;
            return `
                <button type="button" class="stat ${statut}" data-statut="${statut}" aria-pressed="false"
                        title="${statut ? 'Filtrer la liste sur ce statut' : 'Afficher tous les statuts'}">
                    <span class="stat-libelle">${echapper(libelle)}</span>
                    ${statut ? `<span class="stat-part">${part} %</span>` : ''}
                    <span class="stat-nombre">${nombre}</span>
                    <span class="stat-barre"><span style="width: ${statut ? part : 100}%"></span></span>
                </button>`;
        };

        el('stats').innerHTML = carte('', 'Total', total)
            + Object.entries(par_statut).map(([s, n]) => carte(s, LIBELLES_STATUT[s], n)).join('');
        marquerStatActive();
    }

    /** Met en évidence la carte du statut actuellement filtré. */
    function marquerStatActive() {
        document.querySelectorAll('.stat[data-statut]').forEach((c) => {
            c.setAttribute('aria-pressed', String(c.dataset.statut === el('statut').value));
        });
    }

    // ---------- Traitement (cycle de vie) ----------

    async function changerStatut(id, donnees) {
        const { ok, json } = await appelerApi(`/api/demandes/${id}/statut`, { method: 'PATCH', body: JSON.stringify(donnees) });
        if (ok) {
            afficherToast(`Demande n° ${id} : statut « ${json.data.statut_libelle} ».`);
            await charger();
        }
        return { ok, json };
    }

    el('lignes').addEventListener('click', async (e) => {
        const bouton = e.target.closest('button[data-cible]');
        if (!bouton) return;
        const { id, cible } = bouton.dataset;

        if (cible === 'rejetee') {
            demandeARejeter = id;
            el('rejet-id').textContent = id;
            el('rejet-motif').value = '';
            el('rejet-erreur').hidden = true;
            el('dialogue-rejet').showModal();
            return;
        }

        bouton.disabled = true;
        try {
            const { ok, json } = await changerStatut(id, { statut: cible });
            if (!ok) afficherToast(messageErreur(json), true);
        } catch (err) {
            afficherToast("Impossible de joindre l'API.", true);
        } finally {
            bouton.disabled = false;
        }
    });

    el('form-rejet').addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            // Le motif est envoyé tel quel : c'est l'API qui refuse un rejet non motivé.
            const { ok, json } = await changerStatut(demandeARejeter, { statut: 'rejetee', motif: el('rejet-motif').value });
            if (!ok) {
                el('rejet-erreur').textContent = messageErreur(json);
                el('rejet-erreur').hidden = false;
                return;
            }
            el('dialogue-rejet').close();
        } catch (err) {
            el('rejet-erreur').textContent = "Impossible de joindre l'API.";
            el('rejet-erreur').hidden = false;
        }
    });
    el('rejet-annuler').addEventListener('click', () => el('dialogue-rejet').close());

    // ---------- Recherche, filtres et pagination ----------

    el('recherche').addEventListener('submit', (e) => { e.preventDefault(); page = 1; charger(); });
    el('statut').addEventListener('change', () => { page = 1; charger({ avecStats: false }); });
    el('par-page').addEventListener('change', () => { page = 1; charger({ avecStats: false }); });
    el('precedent').addEventListener('click', () => { page--; charger({ avecStats: false }); });
    el('suivant').addEventListener('click', () => { page++; charger({ avecStats: false }); });

    // Un clic sur une carte de statistiques filtre la liste sur ce statut.
    el('stats').addEventListener('click', (e) => {
        const carte = e.target.closest('.stat[data-statut]');
        if (!carte) return;
        el('statut').value = carte.dataset.statut;
        page = 1;
        charger({ avecStats: false });
    });

    document.querySelectorAll('.puce[data-npi]').forEach((puce) => puce.addEventListener('click', () => {
        el('npi').value = puce.dataset.npi;
        el('statut').value = '';
        page = 1;
        charger();
    }));

    // Premier affichage : l'usager de démonstration est chargé directement.
    charger();
</script>
@endverbatim
</body>
</html>
