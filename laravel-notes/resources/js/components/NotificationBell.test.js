import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

import NotificationBell from './NotificationBell.vue';

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        patch: vi.fn(),
    },
}));

const axios = (await import('axios')).default;

const notification = (id, overrides = {}) => ({
    id,
    type: 'note.assigned',
    title: `Powiadomienie ${id}`,
    body: 'Treść powiadomienia.',
    read_at: null,
    created_at: new Date(Date.now() - 3600 * 1000).toISOString(),
    ...overrides,
});

const feedResponse = (items, unreadCount = null) => ({
    data: {
        data: items,
        meta: {
            unread_count: unreadCount ?? items.filter((item) => item.read_at === null).length,
        },
    },
});

const mountReady = async (items, unreadCount = null) => {
    axios.get.mockResolvedValue(feedResponse(items, unreadCount));

    const wrapper = mount(NotificationBell);
    await flushPromises();

    return wrapper;
};

beforeEach(() => {
    vi.clearAllMocks();
});

describe('licznik nieprzeczytanych', () => {
    it('bierze wartosc z meta, a nie z dlugosci listy', async () => {
        // To jest regresja na realny blad: lista jest ucieta do 20 pozycji, wiec
        // liczenie read_at === null w tablicy pokazywalo 20 przy 25 nieprzeczytanych.
        const items = Array.from({ length: 20 }, (_, index) => notification(index + 1));
        const wrapper = await mountReady(items, 25);

        expect(wrapper.vm.notifications).toHaveLength(20);
        expect(wrapper.vm.unreadCount).toBe(25);
        expect(wrapper.find('.badge').text()).toBe('25');
    });

    it('powyzej 99 pokazuje 99+', async () => {
        const wrapper = await mountReady([notification(1)], 150);

        expect(wrapper.find('.badge').text()).toBe('99+');
    });

    it('bez nieprzeczytanych nie ma badge, a dzwonek jest wyciszony', async () => {
        const wrapper = await mountReady([notification(1, { read_at: new Date().toISOString() })], 0);

        expect(wrapper.find('.badge').exists()).toBe(false);
        expect(wrapper.find('i').classes()).toContain('bi-bell');
        expect(wrapper.find('i').classes()).not.toContain('bi-bell-fill');
    });
});

describe('panel', () => {
    it('klik w dzwonek otwiera panel i dociaga swieze dane', async () => {
        const wrapper = await mountReady([notification(1)]);
        const callsBefore = axios.get.mock.calls.length;

        await wrapper.find('button').trigger('click');

        expect(wrapper.vm.panelOpen).toBe(true);
        expect(wrapper.find('.notification-panel').exists()).toBe(true);
        expect(axios.get.mock.calls.length).toBe(callsBefore + 1);
    });

    it('nieprzeczytane maja inne tlo niz przeczytane', async () => {
        const wrapper = await mountReady([
            notification(1),
            notification(2, { read_at: new Date().toISOString() }),
        ]);
        await wrapper.find('button').trigger('click');

        expect(wrapper.findAll('.notification-item')).toHaveLength(2);
        expect(wrapper.findAll('.notification-item--unread')).toHaveLength(1);
    });

    it('pokazuje stan pusty', async () => {
        const wrapper = await mountReady([], 0);
        await wrapper.find('button').trigger('click');

        expect(wrapper.text()).toContain('Brak powiadomień.');
    });

    it('klik poza panelem go zamyka', async () => {
        const wrapper = await mountReady([notification(1)]);
        await wrapper.find('button').trigger('click');
        expect(wrapper.vm.panelOpen).toBe(true);

        document.body.dispatchEvent(new Event('click', { bubbles: true }));
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.panelOpen).toBe(false);
    });

    it('odpina nasluch z dokumentu po zniszczeniu komponentu', async () => {
        const removeSpy = vi.spyOn(document, 'removeEventListener');
        const wrapper = await mountReady([notification(1)]);

        wrapper.unmount();

        expect(removeSpy).toHaveBeenCalledWith('click', expect.any(Function));
        removeSpy.mockRestore();
    });
});

describe('oznaczanie jako przeczytane', () => {
    it('zmienia stan i licznik od razu, przed odpowiedzia serwera', async () => {
        const wrapper = await mountReady([notification(1)], 3);
        let resolveRequest;
        axios.patch.mockReturnValue(new Promise((resolve) => {
            resolveRequest = resolve;
        }));

        wrapper.vm.markAsRead(wrapper.vm.notifications[0]);

        expect(wrapper.vm.notifications[0].read_at).not.toBeNull();
        expect(wrapper.vm.unreadCount).toBe(2);
        expect(axios.patch).toHaveBeenCalledWith('/api/notifications/1/read');

        resolveRequest({
            data: { data: notification(1, { read_at: '2026-07-30T10:00:00+00:00' }), meta: { unread_count: 2 } },
        });
        await flushPromises();

        expect(wrapper.vm.unreadCount).toBe(2);
    });

    it('cofa zmiane i licznik, gdy zadanie padnie', async () => {
        const wrapper = await mountReady([notification(1)], 3);
        axios.patch.mockRejectedValue(new Error('Network Error'));

        wrapper.vm.markAsRead(wrapper.vm.notifications[0]);
        await flushPromises();

        expect(wrapper.vm.notifications[0].read_at).toBeNull();
        expect(wrapper.vm.unreadCount).toBe(3);
        expect(wrapper.vm.error).toContain('Nie udało się oznaczyć');
    });

    it('nie wysyla zadania dla juz przeczytanego', async () => {
        const wrapper = await mountReady([notification(1, { read_at: new Date().toISOString() })], 0);

        wrapper.vm.markAsRead(wrapper.vm.notifications[0]);

        expect(axios.patch).not.toHaveBeenCalled();
    });
});

describe('oznaczanie wszystkich', () => {
    it('zeruje licznik i przelacza tylko nieprzeczytane', async () => {
        const readAt = '2026-07-01T10:00:00+00:00';
        const wrapper = await mountReady([notification(1), notification(2, { read_at: readAt })], 1);

        axios.patch.mockResolvedValue({});
        axios.get.mockResolvedValue(feedResponse([
            notification(1, { read_at: '2026-07-30T10:00:00+00:00' }),
            notification(2, { read_at: readAt }),
        ], 0));

        wrapper.vm.readAll();
        expect(wrapper.vm.unreadCount).toBe(0);

        await flushPromises();

        expect(axios.patch).toHaveBeenCalledWith('/api/notifications/read-all');
        expect(wrapper.vm.notifications.every((item) => item.read_at !== null)).toBe(true);
    });

    it('rollback cofa tylko te powiadomienia, ktore faktycznie przelaczyl', async () => {
        // Rollback po indeksie tablicy bylby zawodny: polling moze podmienic liste
        // w trakcie zadania. Dlatego zapamietujemy identyfikatory.
        const readAt = '2026-07-01T10:00:00+00:00';
        const wrapper = await mountReady([notification(1), notification(2, { read_at: readAt })], 1);

        axios.patch.mockRejectedValue(new Error('Network Error'));

        wrapper.vm.readAll();
        await flushPromises();

        expect(wrapper.vm.notifications[0].read_at).toBeNull();
        // Powiadomienie 2 bylo przeczytane juz przed operacja, wiec musi takie zostac.
        expect(wrapper.vm.notifications[1].read_at).toBe(readAt);
        expect(wrapper.vm.unreadCount).toBe(1);
    });
});

describe('polling', () => {
    it('odpytuje API co 60 sekund i przestaje po zniszczeniu komponentu', async () => {
        vi.useFakeTimers();
        axios.get.mockResolvedValue(feedResponse([notification(1)]));

        const wrapper = mount(NotificationBell);
        expect(axios.get).toHaveBeenCalledTimes(1);

        await vi.advanceTimersByTimeAsync(60 * 1000);
        expect(axios.get).toHaveBeenCalledTimes(2);

        wrapper.unmount();
        await vi.advanceTimersByTimeAsync(60 * 1000);
        expect(axios.get).toHaveBeenCalledTimes(2);

        vi.useRealTimers();
    });

    it('blad odswiezania w tle nie kasuje wczytanej listy', async () => {
        const wrapper = await mountReady([notification(1)], 1);

        axios.get.mockRejectedValue(new Error('Network Error'));
        await wrapper.vm.getNewList({ silent: true });

        expect(wrapper.vm.notifications).toHaveLength(1);
        expect(wrapper.vm.error).toBe('');
    });

    it('blad przy ladowaniu na zadanie uzytkownika pokazuje komunikat', async () => {
        const wrapper = await mountReady([notification(1)], 1);

        axios.get.mockRejectedValue({ response: { data: { message: 'Serwer nie odpowiada.' } } });
        await wrapper.vm.getNewList();

        expect(wrapper.vm.error).toBe('Serwer nie odpowiada.');
    });
});

describe('formatowanie', () => {
    const bell = () => mount(NotificationBell, { data: () => ({ notifications: [] }) }).vm;

    it('czas wzgledny odmienia sie po polsku', async () => {
        axios.get.mockResolvedValue(feedResponse([]));
        const vm = bell();
        const minutesAgo = (minutes) => new Date(Date.now() - minutes * 60 * 1000).toISOString();

        expect(vm.timeAgo(minutesAgo(0.5))).toBe('teraz');
        expect(vm.timeAgo(minutesAgo(1))).toBe('1 minutę temu');
        expect(vm.timeAgo(minutesAgo(3))).toBe('3 minuty temu');
        expect(vm.timeAgo(minutesAgo(5))).toBe('5 minut temu');
        // 12-14 bierze forme mnoga, mimo koncowki 2-4.
        expect(vm.timeAgo(minutesAgo(13))).toBe('13 minut temu');
        expect(vm.timeAgo(minutesAgo(22))).toBe('22 minuty temu');
        expect(vm.timeAgo(minutesAgo(60))).toBe('1 godzinę temu');
        expect(vm.timeAgo(minutesAgo(60 * 3))).toBe('3 godziny temu');
        expect(vm.timeAgo(minutesAgo(60 * 24))).toBe('1 dzień temu');
        expect(vm.timeAgo(minutesAgo(60 * 24 * 5))).toBe('5 dni temu');
        expect(vm.timeAgo(minutesAgo(60 * 24 * 40))).toBe('1 miesiąc temu');
        expect(vm.timeAgo(null)).toBe('');
    });

    it('skraca tresc do zadanej dlugosci', async () => {
        axios.get.mockResolvedValue(feedResponse([]));
        const vm = bell();

        expect(vm.truncate('krótki tekst', 80)).toBe('krótki tekst');
        expect(vm.truncate('a'.repeat(100), 80)).toBe(`${'a'.repeat(80)}…`);
        expect(vm.truncate('   spacje   ', 80)).toBe('spacje');
        expect(vm.truncate(null, 80)).toBe('');
    });
});
