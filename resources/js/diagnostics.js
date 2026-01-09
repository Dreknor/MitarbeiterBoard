import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import axios from 'axios';

// Register Alpine plugins
Alpine.plugin(collapse);

// Make Alpine and axios available globally
window.Alpine = Alpine;
window.axios = axios;

// Configure axios
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Add CSRF token to all requests
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

// Alpine.js Data Components for Diagnostic System

// Main Diagnostic Session Component
Alpine.data('diagnosticSession', () => ({
    ratings: {},
    currentGoals: {},
    stageNotes: {},
    assessmentIds: {}, // Store assessment IDs for goals
    showComments: {}, // Track which goal's comments are shown
    newComment: {}, // Track new comment input for each goal
    saving: false,
    saved: false,
    error: false,

    init() {
        // Initialize existing data from the page
        this.initializeFromPage();
    },

    initializeFromPage() {
        // This will be populated from blade templates
        console.log('Diagnostic Session initialized');
    },

    toggleComments(goalId) {
        this.showComments[goalId] = !this.showComments[goalId];
    },

    setRating(goalId, assessmentId, rating) {
        // Update UI immediately for instant feedback
        this.ratings[goalId] = rating;

        // If rating is not gray, uncheck current goal
        if (rating !== 'gray') {
            this.currentGoals[goalId] = false;
        }

        // Save to backend
        return this.saveAssessment(goalId, rating);
    },

    async saveAssessment(goalId, rating) {
        // Note: ratings[goalId] is already set in setRating for immediate UI update
        this.saving = true;
        this.saved = false;
        this.error = false;

        try {
            const sessionId = this.getSessionId();
            const response = await axios.post(`/diagnostics/session/${sessionId}/assess`, {
                goal_id: goalId,
                rating: rating
            });

            if (response.data.success) {
                this.showSavedIndicator();

                // Store the assessment ID for later use
                if (response.data.assessment_id) {
                    this.assessmentIds[goalId] = response.data.assessment_id;
                }

                // Update current goal state if rating is gray
                if (rating === 'gray') {
                    this.currentGoals[goalId] = response.data.is_current_goal || false;
                } else {
                    this.currentGoals[goalId] = false;
                }
            }
        } catch (err) {
            console.error('Error saving assessment:', err);
            this.error = true;
            // Revert the optimistic update on error
            delete this.ratings[goalId];
            setTimeout(() => this.error = false, 3000);
        } finally {
            this.saving = false;
        }
    },

    async toggleCurrentGoal(goalId, assessmentId, event) {
        const isChecked = event.target.checked;

        // Use stored assessment ID if provided ID is 0
        const actualAssessmentId = assessmentId || this.assessmentIds[goalId];

        if (!actualAssessmentId) {
            console.error('No assessment ID available for goal:', goalId);
            event.target.checked = !isChecked;
            return;
        }

        this.saving = true;
        this.saved = false;
        this.error = false;

        try {
            const response = await axios.post(`/diagnostics/assessment/${actualAssessmentId}/toggle-current`, {
                is_current: isChecked
            });

            if (response.data.success) {
                this.currentGoals[goalId] = isChecked;
                this.showSavedIndicator();
            }
        } catch (err) {
            console.error('Error toggling current goal:', err);
            this.error = true;
            event.target.checked = !isChecked; // Revert checkbox
            setTimeout(() => this.error = false, 3000);
        } finally {
            this.saving = false;
        }
    },

    async saveStageNote(stageId) {
        this.saving = true;
        this.saved = false;
        this.error = false;

        try {
            const sessionId = this.getSessionId();
            const response = await axios.post(`/diagnostics/session/${sessionId}/stage/${stageId}/note`, {
                notes: this.stageNotes[stageId] || ''
            });

            if (response.data.success) {
                this.showSavedIndicator();
            }
        } catch (err) {
            console.error('Error saving stage note:', err);
            this.error = true;
            setTimeout(() => this.error = false, 3000);
        } finally {
            this.saving = false;
        }
    },

    showSavedIndicator() {
        this.saved = true;
        setTimeout(() => this.saved = false, 2000);
    },

    getSessionId() {
        // Extract session ID from URL or data attribute
        const element = document.querySelector('[data-session-id]');
        if (element) {
            return element.dataset.sessionId;
        }
        // Fallback: extract from URL
        const urlParts = window.location.pathname.split('/');
        return urlParts[urlParts.length - 1];
    },

    getSchuelerId() {
        // Extract from data attribute or URL
        const element = document.querySelector('[data-schueler-id]');
        return element ? element.dataset.schuelerId : null;
    },

    async saveInlineComment(goalId, comment) {
        if (!comment || !comment.trim()) return;

        this.saving = true;
        this.saved = false;
        this.error = false;

        try {
            const schuelerId = this.getSchuelerId();
            const response = await axios.post(`/diagnostics/goal/${goalId}/schueler/${schuelerId}/comment`, {
                comment: comment.trim()
            });

            if (response.data.success) {
                // Reset the comment field
                this.newComment[goalId] = '';
                this.showSavedIndicator();
                // Reload the page to show the new comment
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            }
        } catch (err) {
            console.error('Error saving inline comment:', err);
            this.error = true;
            setTimeout(() => this.error = false, 3000);
        } finally {
            this.saving = false;
        }
    }
}));

// Admin Area Component
Alpine.data('diagnosticAdmin', () => ({
    openAccordions: {},
    editMode: {},
    deleteConfirm: {},

    toggleAccordion(id) {
        this.openAccordions[id] = !this.openAccordions[id];
    },

    isOpen(id) {
        return this.openAccordions[id] || false;
    },

    enableEdit(id) {
        this.editMode[id] = true;
    },

    cancelEdit(id) {
        this.editMode[id] = false;
    },

    confirmDelete(id) {
        return confirm('Wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.');
    }
}));


// Diagnostic Admin Component
Alpine.data('diagnosticAdmin', () => ({
    // Inline form states (instead of modals)
    showAreaForm: false,
    showStageForm: {},
    showGoalForm: {},

    // Collapse states
    expandedAreas: {},
    expandedStages: {},


    // Forms
    areaForm: { id: null, name: '', description: '', active: true },
    stageForm: { id: null, area_id: null, name: '', code: '', goal_description: '' },
    goalForm: { id: null, stage_id: null, code: '', description: '' },

    // Toggle Methods
    toggleArea(areaId) {
        this.expandedAreas[areaId] = !this.expandedAreas[areaId];
    },

    toggleStage(stageId) {
        this.expandedStages[stageId] = !this.expandedStages[stageId];
    },

    isAreaExpanded(areaId) {
        return this.expandedAreas[areaId] === true;
    },

    isStageExpanded(stageId) {
        return this.expandedStages[stageId] === true;
    },

    // Area Methods
    openAreaForm() {
        this.areaForm = { id: null, name: '', description: '', active: true };
        this.showAreaForm = true;
    },

    closeAreaForm() {
        this.showAreaForm = false;
        this.areaForm = { id: null, name: '', description: '', active: true };
    },

    editArea(id, name, description, active) {
        this.areaForm = { id, name, description, active };
        this.showAreaForm = true;
        // Scroll to form
        setTimeout(() => {
            document.getElementById('area-form')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
    },

    async saveArea() {
        try {
            const url = this.areaForm.id
                ? `/diagnostics/areas/${this.areaForm.id}`
                : '/diagnostics/areas';

            const method = this.areaForm.id ? 'put' : 'post';

            await axios[method](url, this.areaForm);

            this.closeAreaForm();
            window.location.reload();
        } catch (error) {
            console.error('Fehler:', error);
            alert('Fehler beim Speichern: ' + (error.response?.data?.message || error.message));
        }
    },

    async deleteArea(id, name) {
        if (!confirm(`Bereich "${name}" wirklich löschen? Alle Stufen und Ziele werden ebenfalls gelöscht!`)) {
            return;
        }

        try {
            await axios.delete(`/diagnostics/areas/${id}`);
            window.location.reload();
        } catch (error) {
            console.error('Fehler:', error);
            alert('Fehler beim Löschen: ' + (error.response?.data?.message || error.message));
        }
    },

    // Stage Methods
    openStageForm(areaId) {
        this.stageForm = { id: null, area_id: areaId, name: '', code: '', goal_description: '' };
        this.showStageForm[areaId] = true;
    },

    closeStageForm(areaId) {
        this.showStageForm[areaId] = false;
        this.stageForm = { id: null, area_id: null, name: '', code: '', goal_description: '' };
    },

    editStage(id, areaId, name, code, goalDescription) {
        this.stageForm = { id, area_id: areaId, name, code, goal_description: goalDescription };
        this.showStageForm[areaId] = true;
        this.expandedAreas[areaId] = true;
        setTimeout(() => {
            document.getElementById(`stage-form-${areaId}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
    },

    async saveStage() {
        try {
            const url = this.stageForm.id
                ? `/diagnostics/admin/stages/${this.stageForm.id}`
                : `/diagnostics/admin/areas/${this.stageForm.area_id}/stages`;

            const method = this.stageForm.id ? 'put' : 'post';

            await axios[method](url, this.stageForm);

            this.closeStageForm(this.stageForm.area_id);
            window.location.reload();
        } catch (error) {
            console.error('Fehler:', error);
            alert('Fehler beim Speichern: ' + (error.response?.data?.message || error.message));
        }
    },

    async deleteStage(id, name) {
        if (!confirm(`Stufe "${name}" wirklich löschen? Alle Ziele werden ebenfalls gelöscht!`)) {
            return;
        }

        try {
            await axios.delete(`/diagnostics/admin/stages/${id}`);
            window.location.reload();
        } catch (error) {
            console.error('Fehler:', error);
            alert('Fehler beim Löschen: ' + (error.response?.data?.message || error.message));
        }
    },

    // Goal Methods
    openGoalForm(stageId) {
        this.goalForm = { id: null, stage_id: stageId, code: '', description: '' };
        this.showGoalForm[stageId] = true;
    },

    closeGoalForm(stageId) {
        this.showGoalForm[stageId] = false;
        this.goalForm = { id: null, stage_id: null, code: '', description: '' };
    },

    editGoal(id, stageId, code, description) {
        this.goalForm = { id, stage_id: stageId, code, description };
        this.showGoalForm[stageId] = true;
        this.expandedStages[stageId] = true;
        setTimeout(() => {
            document.getElementById(`goal-form-${stageId}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
    },

    async saveGoal() {
        try {
            const url = this.goalForm.id
                ? `/diagnostics/admin/goals/${this.goalForm.id}`
                : `/diagnostics/admin/stages/${this.goalForm.stage_id}/goals`;

            const method = this.goalForm.id ? 'put' : 'post';

            await axios[method](url, this.goalForm);

            this.closeGoalForm(this.goalForm.stage_id);
            window.location.reload();
        } catch (error) {
            console.error('Fehler:', error);
            alert('Fehler beim Speichern: ' + (error.response?.data?.message || error.message));
        }
    },

    async deleteGoal(id, code) {
        if (!confirm(`Ziel "${code}" wirklich löschen?`)) {
            return;
        }

        try {
            await axios.delete(`/diagnostics/admin/goals/${id}`);
            window.location.reload();
        } catch (error) {
            console.error('Fehler:', error);
            alert('Fehler beim Löschen: ' + (error.response?.data?.message || error.message));
        }
    }
}));

// Enable tooltips initialization (if using a tooltip library)
document.addEventListener('DOMContentLoaded', function() {
    // Initialize any third-party components here
    console.log('Diagnostic system loaded');
});

// Initialize Alpine - MUST be last after all stores and data components are defined
Alpine.start();

