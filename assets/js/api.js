const API_BASE_URL = '/peaceplot/api';

const API = {
    async fetchLots(page = 1, limit = 20, search = '', status = '', section = '', block = '', occupancy = '', sortOrder = 'ASC') {
        try {
            const url = new URL(`${window.location.origin}${API_BASE_URL}/cemetery_lots.php`);
            url.searchParams.append('page', page);
            url.searchParams.append('limit', limit);
            if (search) url.searchParams.append('search', search);
            if (status) url.searchParams.append('status', status);
            if (section) url.searchParams.append('section', section);
            if (block) url.searchParams.append('block', block);
            if (occupancy) url.searchParams.append('occupancy', occupancy);
            if (sortOrder) url.searchParams.append('sort_order', sortOrder);
            
            const response = await fetch(url.toString());
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error fetching lots:', error);
            return { success: false, message: error.message };
        }
    },

    async fetchLot(id) {
        try {
            const response = await fetch(`${API_BASE_URL}/cemetery_lots.php?id=${id}`);
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error fetching lot:', error);
            return { success: false, message: error.message };
        }
    },

    async fetchLatestLotNumber(sectionId = null) {
        try {
            const url = new URL(`${window.location.origin}${API_BASE_URL}/cemetery_lots.php`);
            url.searchParams.append('latest_lot', '1');
            if (sectionId !== null && sectionId !== undefined && String(sectionId).trim() !== '') {
                url.searchParams.append('section_id', sectionId);
            }

            const response = await fetch(url.toString());
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error fetching latest lot number:', error);
            return { success: false, message: error.message };
        }
    },

    async createLot(lotData) {
        try {
            const response = await fetch(`${API_BASE_URL}/cemetery_lots.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(lotData)
            });
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error creating lot:', error);
            return { success: false, message: error.message };
        }
    },

    async updateLot(id, lotData) {
        try {
            const response = await fetch(`${API_BASE_URL}/cemetery_lots.php`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ ...lotData, id })
            });
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error updating lot:', error);
            return { success: false, message: error.message };
        }
    },

    async deleteLot(id) {
        try {
            const response = await fetch(`${API_BASE_URL}/cemetery_lots.php`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id })
            });
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error deleting lot:', error);
            return { success: false, message: error.message };
        }
    },

    async deleteRecord(id, action = 'archive') {
        try {
            const response = await fetch(`${API_BASE_URL}/burial_records.php`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id, action })
            });
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error with record action:', error);
            return { success: false, message: error.message };
        }
    },

    async fetchBurialRecords(page = 1, limit = 10, search = '', status = '', section = '', archived = 0) {
        try {
            const url = new URL(`${window.location.origin}${API_BASE_URL}/burial_records.php`);
            url.searchParams.append('page', page);
            url.searchParams.append('limit', limit);
            if (search) url.searchParams.append('search', search);
            if (status) url.searchParams.append('status', status);
            if (section) url.searchParams.append('section', section);
            if (archived) url.searchParams.append('archived', archived);
            
            const response = await fetch(url.toString());
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error fetching burial records:', error);
            return { success: false, message: error.message };
        }
    },

    async updateBurialRecord(id, recordData) {
        try {
            const response = await fetch(`${API_BASE_URL}/burial_records.php`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ ...recordData, id })
            });
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error updating burial record:', error);
            return { success: false, message: error.message };
        }
    },

    async fetchLotLayers(lotId) {
        try {
            const response = await fetch(`${API_BASE_URL}/lot_layers.php?lot_id=${lotId}`);
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error fetching lot layers:', error);
            return { success: false, message: error.message };
        }
    },

    // Blocks API
    async fetchBlocks() {
        try {
            const response = await fetch(`${API_BASE_URL}/blocks.php`);
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error fetching blocks:', error);
            return { success: false, message: error.message };
        }
    },

    async createBlock(blockData) {
        try {
            const response = await fetch(`${API_BASE_URL}/blocks.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(blockData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error creating block:', error);
            return { success: false, message: error.message };
        }
    },

    async updateBlock(id, blockData) {
        try {
            const response = await fetch(`${API_BASE_URL}/blocks.php`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ...blockData, id })
            });
            return await response.json();
        } catch (error) {
            console.error('Error updating block:', error);
            return { success: false, message: error.message };
        }
    },

    async deleteBlock(id) {
        try {
            const response = await fetch(`${API_BASE_URL}/blocks.php`, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            return await response.json();
        } catch (error) {
            console.error('Error deleting block:', error);
            return { success: false, message: error.message };
        }
    }
};
