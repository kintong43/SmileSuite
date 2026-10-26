import Alpine from 'alpinejs';

Alpine.data('bookingForm', () => ({
    service: '', practitioner: '', chair: '', date: '', slots: [], selected: null, loading: false, message: '', timezone: 'UTC', requestVersion: 0,
    get chosen() { return this.selected === null ? null : this.slots[this.selected]; },
    init() {
        this.timezone = this.$el.dataset.timezone;
        this.service = this.$el.querySelector('[name=service_id]')?.value || '';
        this.date = new Intl.DateTimeFormat('en-CA', { timeZone: this.timezone, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
        this.loadSlots();
    },
    async loadSlots() {
        const version = ++this.requestVersion;
        this.selected = null; this.slots = []; this.loading = true; this.message = 'Finding a good time…';
        const query = new URLSearchParams({ service_id: this.service, date: this.date });
        if (this.practitioner) query.set('practitioner_id', this.practitioner);
        if (this.chair) query.set('operatory_id', this.chair);
        try {
            const response = await fetch(`/appointments/slots?${query}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const result = await response.json();
            if (version !== this.requestVersion) return;
            if (!response.ok) throw new Error('Slots are unavailable. Check your clinic preferences or choose another date.');
            this.slots = result.slots;
            this.message = this.slots.length ? `${this.slots.length} available resource and time combinations` : 'No times available on this date. Try another date or practitioner.';
        } catch (error) { if (version === this.requestVersion) this.message = error.message || 'Could not load availability. Please try again.'; }
        finally { if (version === this.requestVersion) this.loading = false; }
    },
    selectSlot(index) { this.selected = index; },
    formatTime(iso) { return new Intl.DateTimeFormat('en', { timeZone: this.timezone, hour: 'numeric', minute: '2-digit', timeZoneName: 'shortOffset' }).format(new Date(iso)); },
}));

window.Alpine = Alpine;
Alpine.start();
