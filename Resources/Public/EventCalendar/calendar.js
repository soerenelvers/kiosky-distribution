(function () {
  'use strict';

  const locale = document.documentElement.lang || 'de-DE';
  const dateKey = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  const parseDay = value => {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
    return match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]), 12) : null;
  };
  const eventTime = event => new Date(event.start || `${event.date}T12:00:00`);
  const formatTime = value => value ? new Intl.DateTimeFormat(locale, { hour: '2-digit', minute: '2-digit' }).format(new Date(value)) : '';
  const escapeHtml = value => String(value || '').replace(/[&<>'"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character]);
  const searchable = event => [event.title, event.subtitle, event.description, event.publicNotes, event.organizer, event.eventType, event.venue, event.room].filter(Boolean).join(' ').toLocaleLowerCase(locale);

  function eventCard(event, compact) {
    const where = [event.venue, event.room].filter(Boolean).join(' · ');
    const time = event.start ? formatTime(event.start) : '';
    const meta = [time ? `${time} Uhr` : '', where].filter(Boolean).join(' · ');
    const detail = compact ? '' : `${event.subtitle ? `<p class="kiosky-calendar__subtitle">${escapeHtml(event.subtitle)}</p>` : ''}${event.description ? `<p>${escapeHtml(event.description)}</p>` : ''}${event.publicNotes ? `<p class="kiosky-calendar__note">${escapeHtml(event.publicNotes)}</p>` : ''}`;
    const ticket = event.ticketUrl && event.status !== 'cancelled' ? `<a href="${escapeHtml(event.ticketUrl)}" rel="noopener">Tickets / Details</a>` : '';
    const status = event.status === 'cancelled' ? '<strong class="kiosky-calendar__status">Abgesagt</strong>' : event.status === 'sold_out' ? '<strong class="kiosky-calendar__status">Ausverkauft</strong>' : '';
    return `<article class="kiosky-calendar__event${compact ? ' is-compact' : ''}"><time datetime="${escapeHtml(event.start || event.date)}">${new Intl.DateTimeFormat(locale, { weekday: compact ? undefined : 'long', day: '2-digit', month: compact ? '2-digit' : 'long', year: compact ? undefined : 'numeric' }).format(parseDay(event.date))}</time><div><h4>${escapeHtml(event.title)}</h4>${status}${meta ? `<p class="kiosky-calendar__meta">${escapeHtml(meta)}</p>` : ''}${detail}${event.remainingTickets ? `<p>${escapeHtml(event.remainingTickets)}</p>` : ''}${ticket}</div></article>`;
  }

  function initialize(root) {
    if (root.dataset.kioskyReady) return;
    root.dataset.kioskyReady = 'true';
    let payload;
    try { payload = JSON.parse(root.querySelector('[data-kiosky-data]').textContent); } catch { return; }
    const events = Array.isArray(payload.events) ? payload.events : [];
    const settings = payload.settings || {};
    const grid = root.querySelector('[data-kiosky-grid]');
    const caption = root.querySelector('[data-kiosky-caption]');
    const results = root.querySelector('[data-kiosky-results]');
    const upcoming = root.querySelector('[data-kiosky-upcoming]');
    const search = root.querySelector('[data-kiosky-search]');
    const jump = root.querySelector('[data-kiosky-jump]');
    const today = new Date();
    today.setHours(12, 0, 0, 0);
    let visibleMonth = new Date(today.getFullYear(), today.getMonth(), 1, 12);
    let selectedDate = dateKey(today);
    let query = '';

    const filtered = () => query ? events.filter(event => searchable(event).includes(query)) : events;
    function renderResults() {
      const matches = query ? filtered() : events.filter(event => event.date === selectedDate);
      const heading = query ? `${matches.length} Suchergebnis${matches.length === 1 ? '' : 'se'}` : new Intl.DateTimeFormat(locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(parseDay(selectedDate));
      results.innerHTML = `<h3>${escapeHtml(heading)}</h3>${matches.length ? matches.slice(0, 100).map(event => eventCard(event, false)).join('') : '<p>Keine Veranstaltungen gefunden.</p>'}`;
    }

    function renderGrid() {
      const year = visibleMonth.getFullYear();
      const month = visibleMonth.getMonth();
      caption.textContent = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(visibleMonth);
      const firstWeekday = (new Date(year, month, 1, 12).getDay() + 6) % 7;
      const days = new Date(year, month + 1, 0).getDate();
      const counts = new Map();
      filtered().forEach(event => {
        if (event.date.startsWith(`${year}-${String(month + 1).padStart(2, '0')}-`)) counts.set(event.date, (counts.get(event.date) || 0) + 1);
      });
      const cells = Array.from({ length: firstWeekday }, () => '<span class="kiosky-calendar__empty" role="gridcell"></span>');
      for (let day = 1; day <= days; day += 1) {
        const key = dateKey(new Date(year, month, day, 12));
        const count = counts.get(key) || 0;
        cells.push(`<button type="button" role="gridcell" data-kiosky-day="${key}" class="${key === selectedDate ? 'is-selected ' : ''}${key === dateKey(today) ? 'is-today ' : ''}${count ? 'has-events' : ''}" aria-label="${day}. ${caption.textContent}${count ? `, ${count} Veranstaltung${count === 1 ? '' : 'en'}` : ''}"${key === selectedDate ? ' aria-current="date"' : ''}><span>${day}</span>${count ? `<small>${count}</small>` : ''}</button>`);
      }
      grid.innerHTML = cells.join('');
      grid.querySelectorAll('[data-kiosky-day]').forEach(button => button.addEventListener('click', () => {
        selectedDate = button.dataset.kioskyDay;
        if (search) search.value = '';
        query = '';
        renderGrid();
        renderResults();
      }));
    }

    root.querySelectorAll('[data-kiosky-month]').forEach(button => button.addEventListener('click', () => {
      visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() + Number(button.dataset.kioskyMonth), 1, 12);
      renderGrid();
    }));
    root.querySelectorAll('[data-kiosky-year]').forEach(button => button.addEventListener('click', () => {
      visibleMonth = new Date(visibleMonth.getFullYear() + Number(button.dataset.kioskyYear), visibleMonth.getMonth(), 1, 12);
      renderGrid();
    }));
    root.querySelector('[data-kiosky-today]').addEventListener('click', () => {
      selectedDate = dateKey(today);
      visibleMonth = new Date(today.getFullYear(), today.getMonth(), 1, 12);
      if (search) search.value = '';
      query = '';
      renderGrid(); renderResults();
    });
    jump.addEventListener('change', () => {
      const date = parseDay(jump.value);
      if (!date) return;
      selectedDate = dateKey(date);
      visibleMonth = new Date(date.getFullYear(), date.getMonth(), 1, 12);
      renderGrid(); renderResults();
    });
    search?.addEventListener('input', () => {
      query = search.value.trim().toLocaleLowerCase(locale);
      renderGrid(); renderResults();
    });
    if (upcoming) {
      const now = Date.now();
      const future = events.filter(event => eventTime(event).getTime() >= now).slice(0, Number(settings.upcomingCount) || 10);
      upcoming.innerHTML = future.length ? future.map(event => eventCard(event, true)).join('') : '<p>Keine kommenden Veranstaltungen.</p>';
    }
    renderGrid(); renderResults();
  }

  const start = () => document.querySelectorAll('[data-kiosky-event-calendar]').forEach(initialize);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
}());
