import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

import NoteForm from './NoteForm.vue';

vi.mock('axios', () => ({
    default: {
        post: vi.fn(),
        put: vi.fn(),
    },
}));

const axios = (await import('axios')).default;

const note = (id, overrides = {}) => ({
    id,
    title: `Notatka ${id}`,
    content: 'Treść notatki.',
    is_pinned: false,
    ...overrides,
});

/** Odpowiedz 422 w formacie, jaki zwraca Laravel przy bledzie walidacji. */
const validationError = (errors) => ({
    response: { status: 422, data: { message: 'Nieprawidłowe dane.', errors } },
});

beforeEach(() => {
    vi.clearAllMocks();
});

describe('tryb tworzenia i edycji', () => {
    it('bez propa note startuje z pustym formularzem', () => {
        const wrapper = mount(NoteForm);

        expect(wrapper.vm.isEditing).toBe(false);
        expect(wrapper.vm.form).toEqual({ title: '', content: '', is_pinned: false });
        expect(wrapper.text()).toContain('Nowa notatka');
    });

    it('z propem note wypelnia pola i zmienia etykiety', () => {
        const wrapper = mount(NoteForm, {
            props: { note: note(1, { title: 'Zakupy', is_pinned: true }) },
        });

        expect(wrapper.vm.isEditing).toBe(true);
        expect(wrapper.vm.form.title).toBe('Zakupy');
        expect(wrapper.vm.form.is_pinned).toBe(true);
        expect(wrapper.text()).toContain('Edytuj notatkę');
        expect(wrapper.text()).toContain('Zapisz zmiany');
    });
});

describe('watch na propie note', () => {
    it('podmiana edytowanej notatki przeladowuje pola', async () => {
        const wrapper = mount(NoteForm, { props: { note: note(1, { title: 'Pierwsza' }) } });

        await wrapper.setProps({ note: note(2, { title: 'Druga', content: 'Inna treść.' }) });

        expect(wrapper.vm.form.title).toBe('Druga');
        expect(wrapper.vm.form.content).toBe('Inna treść.');
    });

    it('przejscie z edycji na nowa notatke czysci formularz', async () => {
        const wrapper = mount(NoteForm, { props: { note: note(1, { title: 'Pierwsza' }) } });

        await wrapper.setProps({ note: null });

        expect(wrapper.vm.form).toEqual({ title: '', content: '', is_pinned: false });
        expect(wrapper.vm.isEditing).toBe(false);
    });

    it('podmiana notatki czysci bledy z poprzedniego zapisu', async () => {
        axios.put.mockRejectedValue(validationError({ title: ['Tytuł musi mieć co najmniej 3 znaki.'] }));

        const wrapper = mount(NoteForm, { props: { note: note(1) } });
        await wrapper.find('form').trigger('submit');
        await flushPromises();
        expect(wrapper.vm.errors.title).toBeDefined();

        await wrapper.setProps({ note: note(2) });

        expect(wrapper.vm.errors).toEqual({});
        expect(wrapper.vm.generalError).toBe('');
    });
});

describe('zapis', () => {
    it('nowa notatka idzie POST-em i emituje saved', async () => {
        axios.post.mockResolvedValue({ data: { data: note(7, { title: 'Nowa' }) } });

        const wrapper = mount(NoteForm);
        wrapper.vm.form.title = 'Nowa';
        wrapper.vm.form.content = 'Treść.';

        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/api/notes', {
            title: 'Nowa',
            content: 'Treść.',
            is_pinned: false,
        });
        expect(wrapper.emitted('saved')).toHaveLength(1);
        expect(wrapper.emitted('saved')[0][0].id).toBe(7);
    });

    it('edycja idzie PUT-em na wlasciwy identyfikator', async () => {
        axios.put.mockResolvedValue({ data: { data: note(3) } });

        const wrapper = mount(NoteForm, { props: { note: note(3) } });
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(axios.put).toHaveBeenCalledWith('/api/notes/3', expect.any(Object));
        expect(axios.post).not.toHaveBeenCalled();
    });

    it('blokuje przycisk na czas zapisu', async () => {
        let resolveRequest;
        axios.post.mockReturnValue(new Promise((resolve) => {
            resolveRequest = resolve;
        }));

        const wrapper = mount(NoteForm);
        await wrapper.find('form').trigger('submit');

        expect(wrapper.vm.isSaving).toBe(true);
        expect(wrapper.find('button[type="submit"]').attributes('disabled')).toBeDefined();

        resolveRequest({ data: { data: note(1) } });
        await flushPromises();

        expect(wrapper.vm.isSaving).toBe(false);
    });

    it('przycisk Anuluj emituje cancel', async () => {
        const wrapper = mount(NoteForm);

        await wrapper.findAll('button').find((button) => button.text() === 'Anuluj').trigger('click');

        expect(wrapper.emitted('cancel')).toHaveLength(1);
    });
});

describe('obsluga bledow z Laravela', () => {
    it('422 z kluczem errors trafia pod konkretne pola', async () => {
        axios.post.mockRejectedValue(validationError({
            title: ['Tytuł musi mieć co najmniej 3 znaki.'],
            content: ['Treść notatki jest wymagana.'],
        }));

        const wrapper = mount(NoteForm);
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(wrapper.vm.errors.title[0]).toBe('Tytuł musi mieć co najmniej 3 znaki.');
        expect(wrapper.text()).toContain('Tytuł musi mieć co najmniej 3 znaki.');
        expect(wrapper.text()).toContain('Treść notatki jest wymagana.');
        expect(wrapper.findAll('.is-invalid')).toHaveLength(2);
        expect(wrapper.emitted('saved')).toBeUndefined();
    });

    it('422 bez errors to regula biznesowa, wiec komunikat ogolny', async () => {
        // Tak wyglada odpowiedz przy przekroczeniu limitu 100 notatek.
        axios.post.mockRejectedValue({
            response: { status: 422, data: { message: 'Osiągnięto limit 100 notatek.' } },
        });

        const wrapper = mount(NoteForm);
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(wrapper.vm.generalError).toBe('Osiągnięto limit 100 notatek.');
        expect(wrapper.vm.errors).toEqual({});
        expect(wrapper.find('.alert-danger').text()).toContain('Osiągnięto limit 100 notatek.');
    });

    it('brak odpowiedzi z serwera daje komunikat zapasowy', async () => {
        axios.post.mockRejectedValue(new Error('Network Error'));

        const wrapper = mount(NoteForm);
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(wrapper.vm.generalError).toBe('Nie udało się zapisać notatki. Spróbuj ponownie.');
    });

    it('kolejna proba zapisu czysci poprzednie bledy', async () => {
        axios.post.mockRejectedValueOnce(validationError({ title: ['Tytuł jest wymagany.'] }));

        const wrapper = mount(NoteForm);
        await wrapper.find('form').trigger('submit');
        await flushPromises();
        expect(wrapper.vm.errors.title).toBeDefined();

        axios.post.mockResolvedValueOnce({ data: { data: note(1) } });
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(wrapper.vm.errors).toEqual({});
        expect(wrapper.emitted('saved')).toHaveLength(1);
    });
});
