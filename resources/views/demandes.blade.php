<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Suivi des demandes d'actes</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f4f6f8; color: #1f2933; }
        main { max-width: 1040px; margin: 0 auto; padding: 24px 16px; }
        h1 { font-size: 1.4rem; margin: 0 0 16px; }
        h2 { font-size: 1.1rem; margin: 0 0 12px; }
        section { background: #fff; border-radius: 8px; padding: 16px; margin-bottom: 20px; }
        form { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-end; margin-bottom: 12px; }
        label { display: flex; flex-direction: column; gap: 4px; font-size: 0.85rem; color: #52606d; }
        input, select, button { padding: 8px 10px; font-size: 0.95rem; border: 1px solid #cbd2d9; border-radius: 6px; }
        button { background: #0b6e4f; color: #fff; border: none; cursor: pointer; }
        button:disabled { background: #9aa5b1; cursor: default; }
        button.action { padding: 4px 8px; font-size: 0.8rem; margin: 2px; }
        button.rejeter { background: #b42318; }
        .stats { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
        .stat { background: #f4f6f8; border-radius: 6px; padding: 8px 12px; font-size: 0.9rem; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #e4e7eb; }
        th { background: #e4e7eb; }
        .badge { padding: 2px 8px; border-radius: 10px; font-size: 0.8rem; white-space: nowrap; }
        .deposee { background: #e0e8f9; } .en_cours { background: #fff3c4; }
        .validee { background: #c6f7e2; } .rejetee { background: #ffd6d6; }
        .message { margin-bottom: 12px; }
        .message:empty { display: none; }
        .message.erreur { color: #b42318; }
        .message.succes { color: #0b6e4f; }
        .pagination { display: flex; gap: 8px; align-items: center; margin-top: 12px; }
        .aide { font-size: 0.85rem; color: #52606d; margin: 0 0 12px; }
    </style>
</head>
<body>
<main>
    <h1>Suivi des demandes d'actes</h1>

    {{-- Dépôt d'une demande (POST /api/demandes) --}}
    <section>
        <h2>Déposer une demande</h2>
        {{-- novalidate : les contrôles sont faits par l'API, dont les messages sont affichés tels quels. --}}
        <form id="depot" novalidate>
            <label>NPI
                <input id="depot-npi" placeholder="10 chiffres" maxlength="10" inputmode="numeric">
            </label>
            <label>Type d'acte
                <select id="depot-type">
                    <option value="">— Choisir —</option>
                    @foreach (\App\Enums\TypeActe::cases() as $type)
                        <option value="{{ $type->value }}">{{ $type->libelle() }}</option>
                    @endforeach
                </select>
            </label>
            <label>Nombre de copies
                <input id="depot-copies" type="number" min="1" max="5" value="1">
            </label>
            <button type="submit">Déposer</button>
        </form>
        <div id="message-depot" class="message"></div>
    </section>

    {{-- Consultation et traitement (GET /api/usagers/{npi}/demandes, PATCH /api/demandes/{id}/statut) --}}
    <section>
        <h2>Demandes d'un usager</h2>
        <form id="recherche">
            <label>NPI
                <input id="npi" placeholder="10 chiffres" maxlength="10" inputmode="numeric" value="1234567890" required>
            </label>
            <label>Statut
                <select id="statut">
                    <option value="">Tous les statuts</option>
                    @foreach (\App\Enums\StatutDemande::cases() as $statut)
                        <option value="{{ $statut->value }}">{{ $statut->libelle() }}</option>
                    @endforeach
                </select>
            </label>
            <label>Par page
                <select id="par-page">
                    <option value="5">5</option>
                    <option value="10">10</option>
                    <option value="20" selected>20 (maximum)</option>
                </select>
            </label>
            <button type="submit">Afficher</button>
        </form>
        <p class="aide">Données de démonstration : NPI 1234567890 (tous les statuts), NPI 1111111111 (25 demandes, sur 2 pages).</p>

        <div id="message-liste" class="message"></div>
        <div id="stats" class="stats"></div>

        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>N°</th><th>Type d'acte</th><th>Copies</th><th>Statut</th><th>Motif de rejet</th><th>Déposée le</th><th>Actions</th></tr>
                </thead>
                <tbody id="lignes"><tr><td colspan="7">Saisissez un NPI puis cliquez sur « Afficher ».</td></tr></tbody>
            </table>
        </div>

        <div class="pagination">
            <button id="precedent" disabled>Précédent</button>
            <span id="info-page"></span>
            <button id="suivant" disabled>Suivant</button>
        </div>
    </section>
</main>

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
    let page = 1;

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

    function afficherMessage(id, texte, type) {
        el(id).textContent = texte;
        el(id).className = `message ${type}`;
    }

    // ---------- Dépôt ----------

    el('depot').addEventListener('submit', async (e) => {
        e.preventDefault();
        const copies = el('depot-copies').value.trim();
        const donnees = {
            npi: el('depot-npi').value.trim(),
            type_acte: el('depot-type').value,
            nombre_copies: copies === '' ? null : Number(copies),
        };

        try {
            const { ok, json } = await appelerApi('/api/demandes', { method: 'POST', body: JSON.stringify(donnees) });
            if (!ok) {
                afficherMessage('message-depot', messageErreur(json), 'erreur');
                return;
            }
            const d = json.data;
            afficherMessage('message-depot', `Demande n° ${d.id} déposée (${d.type_acte_libelle}, ${d.nombre_copies} copie(s)) : statut « ${d.statut_libelle} ».`, 'succes');

            // Affiche directement la liste de l'usager qui vient de déposer.
            el('npi').value = d.npi;
            el('statut').value = '';
            page = 1;
            charger();
        } catch (err) {
            afficherMessage('message-depot', "Impossible de joindre l'API.", 'erreur');
        }
    });

    // ---------- Consultation ----------

    async function charger() {
        const npi = el('npi').value.trim();
        const statut = el('statut').value;
        afficherMessage('message-liste', '', '');

        const params = new URLSearchParams({ page, par_page: el('par-page').value });
        if (statut) params.set('statut', statut);

        try {
            const [liste, stats] = await Promise.all([
                appelerApi(`/api/usagers/${encodeURIComponent(npi)}/demandes?${params}`),
                appelerApi(`/api/statistiques?npi=${encodeURIComponent(npi)}`),
            ]);

            if (!liste.ok) {
                afficherMessage('message-liste', messageErreur(liste.json), 'erreur');
                el('lignes').innerHTML = '';
                el('stats').innerHTML = '';
                afficherPagination(null);
                return;
            }

            afficherLignes(liste.json.data);
            afficherPagination(liste.json.meta);
            if (stats.ok) afficherStats(stats.json.data);
        } catch (err) {
            afficherMessage('message-liste', "Impossible de joindre l'API.", 'erreur');
        }
    }

    function afficherLignes(demandes) {
        el('lignes').innerHTML = demandes.length === 0
            ? '<tr><td colspan="7">Aucune demande.</td></tr>'
            : demandes.map((d) => `
                <tr>
                    <td>${d.id}</td>
                    <td>${echapper(d.type_acte_libelle)}</td>
                    <td>${d.nombre_copies}</td>
                    <td><span class="badge ${d.statut}">${echapper(d.statut_libelle)}</span></td>
                    <td>${echapper(d.motif_rejet ?? '')}</td>
                    <td>${new Date(d.cree_le).toLocaleString('fr-FR')}</td>
                    <td>${d.transitions_possibles.map((cible) => `
                        <button class="action ${cible === 'rejetee' ? 'rejeter' : ''}" data-id="${d.id}" data-cible="${cible}">
                            ${LIBELLES_ACTION[cible]}
                        </button>`).join('') || '—'}
                    </td>
                </tr>`).join('');
    }

    function afficherPagination(meta) {
        el('info-page').textContent = meta ? `Page ${meta.current_page} / ${meta.last_page} (${meta.total} demande(s))` : '';
        el('precedent').disabled = !meta || meta.current_page <= 1;
        el('suivant').disabled = !meta || meta.current_page >= meta.last_page;
    }

    function afficherStats(stats) {
        el('stats').innerHTML = Object.entries(stats.par_statut)
            .map(([s, n]) => `<div class="stat"><strong>${n}</strong> ${echapper(LIBELLES_STATUT[s])}</div>`).join('');
    }

    // ---------- Traitement (cycle de vie) ----------

    el('lignes').addEventListener('click', async (e) => {
        const bouton = e.target.closest('button[data-cible]');
        if (!bouton) return;

        const { id, cible } = bouton.dataset;
        const donnees = { statut: cible };

        if (cible === 'rejetee') {
            const motif = prompt('Motif du rejet (obligatoire) :');
            if (motif === null) return; // annulé par l'agent
            donnees.motif = motif;
        }

        try {
            const { ok, json } = await appelerApi(`/api/demandes/${id}/statut`, { method: 'PATCH', body: JSON.stringify(donnees) });
            if (!ok) {
                afficherMessage('message-liste', messageErreur(json), 'erreur');
                return;
            }
            await charger();
            afficherMessage('message-liste', `Demande n° ${id} : statut « ${json.data.statut_libelle} ».`, 'succes');
        } catch (err) {
            afficherMessage('message-liste', "Impossible de joindre l'API.", 'erreur');
        }
    });

    el('recherche').addEventListener('submit', (e) => { e.preventDefault(); page = 1; charger(); });
    el('precedent').addEventListener('click', () => { page--; charger(); });
    el('suivant').addEventListener('click', () => { page++; charger(); });
</script>
@endverbatim
</body>
</html>
