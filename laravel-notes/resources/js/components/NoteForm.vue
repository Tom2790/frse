<template>
    <form class="border rounded p-3 bg-body-tertiary" novalidate @submit.prevent="submit">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">{{ isEditing ? 'Edytuj notatkę' : 'Nowa notatka' }}</h6>
            <button
                type="button"
                class="btn-close"
                aria-label="Zamknij formularz"
                @click="$emit('cancel')"
            ></button>
        </div>

        <!-- Błąd niedotyczący pojedynczego pola (np. limit notatek, 500, brak sieci) -->
        <div v-if="generalError" class="alert alert-danger py-2">
            {{ generalError }}
        </div>

        <div class="mb-3">
            <label class="form-label" for="note-title">Tytuł</label>
            <input
                id="note-title"
                v-model="form.title"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': errors.title }"
                maxlength="255"
                :disabled="isSaving"
            >
            <!-- Komunikaty pochodzą z odpowiedzi 422 Laravela, nie z walidacji w JS —
                 backend jest jedynym źródłem prawdy o poprawności danych. -->
            <div v-if="errors.title" class="invalid-feedback">{{ errors.title[0] }}</div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="note-content">Treść</label>
            <textarea
                id="note-content"
                v-model="form.content"
                class="form-control"
                :class="{ 'is-invalid': errors.content }"
                rows="4"
                :disabled="isSaving"
            ></textarea>
            <div v-if="errors.content" class="invalid-feedback">{{ errors.content[0] }}</div>
        </div>

        <div class="form-check mb-3">
            <input
                id="note-pinned"
                v-model="form.is_pinned"
                class="form-check-input"
                type="checkbox"
                :disabled="isSaving"
            >
            <label class="form-check-label" for="note-pinned">Przypnij na górze listy</label>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" :disabled="isSaving">
                <span v-if="isSaving" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                {{ isEditing ? 'Zapisz zmiany' : 'Utwórz notatkę' }}
            </button>
            <button type="button" class="btn btn-outline-secondary" :disabled="isSaving" @click="$emit('cancel')">
                Anuluj
            </button>
        </div>
    </form>
</template>

<script>
import axios from 'axios';

/** Pusty formularz — jedno źródło prawdy o stanie początkowym. */
const emptyForm = () => ({ title: '', content: '', is_pinned: false });

export default {
    name: 'NoteForm',

    props: {
        /**
         * Notatka do edycji przekazana z rodzica (props w dół).
         * `null` oznacza tryb tworzenia nowej notatki.
         */
        note: {
            type: Object,
            default: null,
        },
    },

    // Zdarzenia w górę: rodzic decyduje, co zrobić po zapisie i anulowaniu.
    emits: ['saved', 'cancel'],

    data() {
        return {
            form: emptyForm(),
            errors: {},
            generalError: '',
            isSaving: false,
        };
    },

    computed: {
        isEditing() {
            return this.note !== null;
        },
    },

    watch: {
        /**
         * Rodzic trzyma NoteForm zamontowany i tylko podmienia prop `note`
         * (edycja notatki A → edycja notatki B → nowa notatka). Bez tego watchera
         * w polach zostałyby dane poprzedniej notatki.
         *
         * `immediate: true` obsługuje też pierwsze wyrenderowanie.
         */
        note: {
            immediate: true,
            handler(note) {
                this.form = note === null
                    ? emptyForm()
                    : { title: note.title, content: note.content, is_pinned: note.is_pinned };

                this.errors = {};
                this.generalError = '';
            },
        },
    },

    methods: {
        submit() {
            this.isSaving = true;
            this.errors = {};
            this.generalError = '';

            const request = this.isEditing
                ? axios.put(`/api/notes/${this.note.id}`, this.form)
                : axios.post('/api/notes', this.form);

            request
                .then(({ data }) => {
                    this.$emit('saved', data.data);
                })
                .catch((error) => this.handleError(error))
                .finally(() => {
                    this.isSaving = false;
                });
        },

        /**
         * Obsługa odpowiedzi błędnych z Laravela:
         *  - 422 z kluczem `errors` → komunikaty pod konkretnymi polami (walidacja),
         *  - 422 bez `errors` → naruszenie reguły biznesowej (np. limit 100 notatek)
         *    pokazywane jako komunikat ogólny,
         *  - pozostałe (500, brak sieci) → komunikat ogólny z fallbackiem.
         */
        handleError(error) {
            const response = error.response;

            if (response?.status === 422 && response.data?.errors) {
                this.errors = response.data.errors;

                return;
            }

            this.generalError = response?.data?.message ?? 'Nie udało się zapisać notatki. Spróbuj ponownie.';
        },
    },
};
</script>
