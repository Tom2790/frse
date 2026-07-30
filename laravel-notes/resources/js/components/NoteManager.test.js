import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

import NoteManager from './NoteManager.vue';

// Fabryka mocka zamiast automocka - axios jest zbyt zlozony, zeby vitest zgadl kształt.
vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
    },
}));

const axios = (await import('axios')).default;

const note = (id, overrides = {}) => ({
    id,
    title: `Notatka ${id}`,
    content: 'Treść notatki.',
    is_pinned: false,
    created_at: '2026-07-20T10:00:00+00:00',
    updated_at: '2026-07-20T10:00:00+00:00',
    ...overrides,
});

const listResponse = (notes, meta = {}) => ({
    data: {
        data: notes,
        meta: {
            current_page: 1,
            per_page: 15,
            last_page: 1,
            total: notes.length,
            pinned_total: notes.filter((item) => item.is_pinned).length,
            ...meta,
        },
    },
});

/** Montuje komponent i czeka, az skonczy sie pobieranie listy z mounted(). */
const mountReady = async (notes, meta) => {
    axios.get.mockResolvedValue(listResponse(notes, meta));

    const wrapper = mount(NoteManager);
    await flushPromises();

    return wrapper;
};

beforeEach(() => {
    vi.clearAllMocks();
});

afterEach(() => {
    vi.restoreAllMocks();
});

describe('pobieranie listy', () => {
    it('pobiera notatki przy mounted i wypelnia liczniki z meta', async () => {
        const wrapper = await mountReady([note(1, { is_pinned: true }), note(2)], {
            total: 24,
            pinned_total: 4,
            last_page: 2,
        });

        expect(axios.get).toHaveBeenCalledWith('/api/notes', { params: { page: 1 } });
        expect(wrapper.vm.count).toBe(24);
        expect(wrapper.vm.countPinned).toBe(4);
        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.text()).toContain('Notatki (24 | Przypięte: 4)');
    });

    it('liczniki biora sie z meta, a nie z dlugosci strony', async () => {
        // Strona ma 2 notatki, ale uzytkownik ma ich 24. Liczenie z tablicy dawaloby 2.
        const wrapper = await mountReady([note(1), note(2)], { total: 24, pinned_total: 4 });

        expect(wrapper.vm.note_list).toHaveLength(2);
        expect(wrapper.vm.count).toBe(24);
    });

    it('pokazuje skeleton w trakcie pierwszego ladowania', async () => {
        // Zadanie, ktore nigdy sie nie konczy, czyli komponent zostaje w stanie ladowania.
        axios.get.mockReturnValue(new Promise(() => {}));

        const wrapper = mount(NoteManager);
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.skeleton-line').exists()).toBe(true);
        expect(wrapper.find('[aria-busy="true"]').exists()).toBe(true);
    });

    it('pokazuje komunikat i przycisk ponowienia, gdy API padnie', async () => {
        axios.get.mockRejectedValue({ response: { data: { message: 'Coś się zepsuło.' } } });

        const wrapper = mount(NoteManager);
        await flushPromises();

        expect(wrapper.text()).toContain('Coś się zepsuło.');
        expect(wrapper.find('.alert-danger').exists()).toBe(true);
    });

    it('rozroznia pusta liste od pustego wyniku filtrowania', async () => {
        const empty = await mountReady([]);
        expect(empty.text()).toContain('Nie masz jeszcze żadnych notatek');

        const wrapper = await mountReady([note(1, { title: 'Zakupy' })]);
        wrapper.vm.search = 'nieistniejace';
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Żadna notatka na tej stronie nie pasuje');
    });
});

describe('filtrowanie po tytule', () => {
    it('filtruje lokalnie, bez dodatkowego zapytania do API', async () => {
        const wrapper = await mountReady([
            note(1, { title: 'Lista zakupów' }),
            note(2, { title: 'Raport miesięczny' }),
            note(3, { title: 'Zakupy na weekend' }),
        ]);

        const callsBefore = axios.get.mock.calls.length;

        wrapper.vm.search = 'zakup';
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.filteredList.map((item) => item.id)).toEqual([1, 3]);
        expect(axios.get.mock.calls).toHaveLength(callsBefore);
    });

    it('ignoruje wielkosc liter i biale znaki na brzegach', async () => {
        const wrapper = await mountReady([note(1, { title: 'Lista Zakupów' }), note(2, { title: 'Raport' })]);

        wrapper.vm.search = '  ZAKUPÓW  ';
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.filteredList).toHaveLength(1);
    });

    it('pusty filtr zwraca cala strone', async () => {
        const wrapper = await mountReady([note(1), note(2), note(3)]);

        wrapper.vm.search = '   ';
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.filteredList).toHaveLength(3);
    });
});

describe('przypinanie', () => {
    it('zmienia stan od razu, przed odpowiedzia serwera', async () => {
        const wrapper = await mountReady([note(1)]);
        let resolveRequest;
        axios.patch.mockReturnValue(new Promise((resolve) => {
            resolveRequest = resolve;
        }));

        wrapper.vm.togglePin(wrapper.vm.note_list[0]);

        // Zadanie jeszcze nie wrocilo, a UI juz pokazuje przypiecie.
        expect(wrapper.vm.note_list[0].is_pinned).toBe(true);
        expect(wrapper.vm.countPinned).toBe(1);
        expect(axios.patch).toHaveBeenCalledWith('/api/notes/1', { is_pinned: true });

        resolveRequest({ data: { data: note(1, { is_pinned: true }) } });
        await flushPromises();

        expect(wrapper.vm.note_list[0].is_pinned).toBe(true);
    });

    it('cofa zmiane i licznik, gdy zadanie padnie', async () => {
        const wrapper = await mountReady([note(1, { is_pinned: true })], { pinned_total: 1 });
        axios.patch.mockRejectedValue({ response: { data: { message: 'Brak połączenia.' } } });

        wrapper.vm.togglePin(wrapper.vm.note_list[0]);
        expect(wrapper.vm.note_list[0].is_pinned).toBe(false);
        expect(wrapper.vm.countPinned).toBe(0);

        await flushPromises();

        expect(wrapper.vm.note_list[0].is_pinned).toBe(true);
        expect(wrapper.vm.countPinned).toBe(1);
        expect(wrapper.vm.error).toBe('Brak połączenia.');
    });
});

describe('usuwanie', () => {
    it('nie wysyla niczego, gdy uzytkownik anuluje potwierdzenie', async () => {
        const wrapper = await mountReady([note(1)]);
        vi.spyOn(window, 'confirm').mockReturnValue(false);

        wrapper.vm.deleteNote(1);

        expect(axios.delete).not.toHaveBeenCalled();
    });

    it('usuwa i odswieza liste po potwierdzeniu', async () => {
        const wrapper = await mountReady([note(1), note(2)]);
        vi.spyOn(window, 'confirm').mockReturnValue(true);
        axios.delete.mockResolvedValue({});
        axios.get.mockResolvedValue(listResponse([note(2)]));

        wrapper.vm.deleteNote(1);
        await flushPromises();

        expect(axios.delete).toHaveBeenCalledWith('/api/notes/1');
        expect(wrapper.vm.note_list.map((item) => item.id)).toEqual([2]);
    });
});

describe('formularz i polling', () => {
    it('przekazuje notatke do edycji w dol, a po zapisie zamyka i odswieza', async () => {
        const wrapper = await mountReady([note(1, { title: 'Do edycji' })]);

        wrapper.vm.openForm(wrapper.vm.note_list[0]);
        await wrapper.vm.$nextTick();

        const form = wrapper.findComponent({ name: 'NoteForm' });
        expect(form.exists()).toBe(true);
        expect(form.props('note').title).toBe('Do edycji');

        await form.vm.$emit('saved');
        await flushPromises();

        expect(wrapper.vm.showForm).toBe(false);
        expect(wrapper.vm.editNote).toBeNull();
    });

    it('odswieza liste co 3 minuty i czysci interwal po zniszczeniu', async () => {
        vi.useFakeTimers();
        axios.get.mockResolvedValue(listResponse([note(1)]));

        // Liczymy same wywolania axios.get - sa synchroniczne, wiec nie musimy
        // domykac promise'ow i test nie zalezy od kolejnosci microtaskow.
        const wrapper = mount(NoteManager);
        expect(axios.get).toHaveBeenCalledTimes(1);

        await vi.advanceTimersByTimeAsync(3 * 60 * 1000);
        expect(axios.get).toHaveBeenCalledTimes(2);

        wrapper.unmount();
        await vi.advanceTimersByTimeAsync(3 * 60 * 1000);

        // Po unmount interwal nie moze dalej strzelac do API.
        expect(axios.get).toHaveBeenCalledTimes(2);

        vi.useRealTimers();
    });
});
