// Leash dashboard (PLAN §3.7): polls the JSON routes every second; no build step.
(() => {
    const $ = (id) => document.getElementById(id);
    const el = (tag, cls, text) => {
        const e = document.createElement(tag);
        if (cls) e.className = cls;
        if (text !== undefined) e.textContent = text;
        return e;
    };
    const dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    if (window.mermaid) window.mermaid.initialize({ startOnLoad: false, theme: dark ? 'dark' : 'default', securityLevel: 'strict' });

    let tab = 'werewolf';
    document.querySelectorAll('[data-tab]').forEach((button) => button.addEventListener('click', () => {
        tab = button.dataset.tab;
        document.querySelectorAll('[data-tab]').forEach((b) => b.setAttribute('aria-selected', String(b === button)));
        document.querySelectorAll('.tab').forEach((t) => { t.hidden = t.id !== tab; });
        poll();
    }));

    // --- Werewolf --------------------------------------------------------------------------------------------
    const werewolf = $('werewolf');
    const reveal = $('reveal');
    const game = $('game');
    let lastWerewolf = '';

    const chips = (items, cls) => {
        const box = el('div', 'chips');
        if (items.length === 0) box.append(el('span', 'chip none', 'nothing'));
        items.forEach((name) => box.append(el('span', 'chip ' + cls, name)));
        return box;
    };

    const renderSeat = (seat, view) => {
        const card = el('article', 'seat');
        const onTurn = seat.tools.some((t) => !t.readOnly);
        if (!seat.alive) card.classList.add('dead');
        if (onTurn && !view.winner) card.classList.add('on-turn');
        const head = el('header');
        head.append(el('strong', '', `${seat.seat} · ${seat.name}${seat.alive ? '' : ' ✝'}${seat.seat === view.humanSeat ? ' 👤' : ''}`));
        if (seat.role) head.append(el('span', 'role ' + seat.role, seat.role));
        card.append(head, el('small', '', 'tools'), chips(seat.tools.map((t) => t.name), 'tool'),
            el('small', '', 'resources'), chips(seat.resources.map((r) => r.name), ''));
        seat.resources.filter((r) => r.content !== null).forEach((r) => {
            const d = el('details');
            d.append(el('summary', '', r.name), el('pre', '', JSON.stringify(r.content, null, 1)));
            card.append(d);
        });
        if (view.reveal && seat.roles.length) card.append(el('details', '', 'roles: ' + seat.roles.join(', ')));
        return card;
    };

    const renderWerewolf = (view) => {
        const status = $('status');
        status.textContent = `Day ${view.day} · ${view.phase.replace('_', ' ')}`;
        if (view.winner) status.append(' · ', el('span', 'winner', `${view.winner} win`));
        $('seats').replaceChildren(...view.seats.map((s) => renderSeat(s, view)));
        const log = $('log');
        const atBottom = log.scrollHeight - log.scrollTop - log.clientHeight < 40;
        log.replaceChildren(...view.events.map((e) => {
            const li = el('li', e.audience === 'public' ? '' : 'private');
            if (['seat_killed', 'seat_eliminated', 'game_over'].includes(e.type)) li.classList.add('death');
            li.append(el('span', 'when', `D${e.day} ${e.phase.replace('_', ' ')}${e.audience === 'public' ? '' : ' · ' + e.audience}`), e.text);
            return li;
        }));
        if (atBottom) log.scrollTop = log.scrollHeight;
        const denials = view.denials.map((d) => el('li', '', `Day ${d.day}: ${d.caller} called ${d.capability} in ${d.places.join(', ')} — denied`));
        $('denials').replaceChildren(...(denials.length ? denials : [el('li', 'none', 'none yet')]));
    };

    const pollWerewolf = async () => {
        const url = werewolf.dataset.urlTemplate.replace('__ID__', encodeURIComponent(game.value)) + (reveal.checked ? '?reveal=1' : '');
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) { $('status').textContent = response.status === 404 ? 'waiting for the game…' : 'error ' + response.status; return; }
        const text = await response.text();
        if (text === lastWerewolf) return;
        lastWerewolf = text;
        renderWerewolf(JSON.parse(text));
    };
    reveal.addEventListener('change', () => { lastWerewolf = ''; poll(); });
    game.addEventListener('change', () => { lastWerewolf = ''; poll(); });

    // --- Doc Review ------------------------------------------------------------------------------------------
    const docReview = $('doc-review');
    let lastDocs = '';

    const renderDoc = (doc) => {
        const card = el('article', 'doc');
        const left = el('div');
        left.append(el('h3', '', `${doc.id} · ${doc.headline || doc.title || 'untitled'} — ${doc.status.replace('_', ' ')}`), el('p', 'brief', doc.brief));
        const diagram = el('pre', 'mermaid', doc.diagram);
        left.append(diagram);
        const timeline = el('ol', 'timeline');
        doc.timeline.forEach((t) => {
            const li = el('li', t.kind);
            li.append(el('span', 'actor', t.actor), t.text);
            timeline.append(li);
        });
        if (doc.timeline.length === 0) timeline.append(el('li', '', 'nothing yet'));
        const right = el('div');
        right.append(el('h2', '', 'Timeline'), timeline);
        card.append(left, right);
        return card;
    };

    const pollDocReview = async () => {
        const response = await fetch(docReview.dataset.url, { headers: { Accept: 'application/json' } });
        if (!response.ok) return;
        const text = await response.text();
        if (text === lastDocs) return;
        lastDocs = text;
        const docs = JSON.parse(text).documents;
        $('documents').replaceChildren(...docs.map(renderDoc));
        if (window.mermaid) await window.mermaid.run({ querySelector: '#documents pre.mermaid' });
    };

    let busy = false;
    const poll = async () => {
        if (busy) return;
        busy = true;
        try { await (tab === 'werewolf' ? pollWerewolf() : pollDocReview()); } catch (e) { console.error(e); } finally { busy = false; }
    };
    poll();
    setInterval(poll, 1000);
})();
