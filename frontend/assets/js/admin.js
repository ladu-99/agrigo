/**
 * AgriConnect - Admin JavaScript
 * Admin-specific utility functions
 */

const AdminAPI = {
    /**
     * Get all extension officers
     */
    async getExtensionOfficers() {
        return API.get('../../backend/api/admin/extension-officers.php');
    },

    /**
     * Create a new extension officer
     */
    async createExtensionOfficer(data) {
        return API.post('../../backend/api/admin/extension-officers.php', data);
    },

    /**
     * Update an extension officer
     */
    async updateExtensionOfficer(data) {
        return API.request('../../backend/api/admin/extension-officers.php', {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    },

    /**
     * Get all users
     */
    async getUsers(filters = {}) {
        const params = new URLSearchParams(filters).toString();
        return API.get('../../backend/api/admin/users.php' + (params ? '?' + params : ''));
    },

    /**
     * Get all locations
     */
    async getLocations() {
        return API.get('../../backend/api/admin/locations.php');
    },

    /**
     * Create a location
     */
    async createLocation(data) {
        return API.post('../../backend/api/admin/locations.php', data);
    },

    /**
     * Get all specializations
     */
    async getSpecializations() {
        return API.get('../../backend/api/admin/specializations.php');
    },

    /**
     * Get opportunities
     */
    async getOpportunities() {
        return API.get('../../backend/api/admin/opportunities.php');
    },

    /**
     * Create an opportunity
     */
    async createOpportunity(data) {
        return API.post('../../backend/api/admin/opportunities.php', data);
    },

    /**
     * Update an opportunity
     */
    async updateOpportunity(data) {
        return API.request('../../backend/api/admin/opportunities.php', {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    },

    /**
     * Get success stories
     */
    async getSuccessStories(status = '') {
        return API.get('../../backend/api/admin/success-stories.php' + (status ? '?status=' + status : ''));
    },

    /**
     * Approve/reject a success story
     */
    async reviewStory(storyId, action) {
        return API.request('../../backend/api/admin/success-stories.php', {
            method: 'PUT',
            body: JSON.stringify({ id: storyId, action: action })
        });
    }
};
