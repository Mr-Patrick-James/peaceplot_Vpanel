document.addEventListener('DOMContentLoaded', () => {
    // UI Elements
    const modal = document.getElementById('sectionModal');
    const form = document.getElementById('sectionForm');
    const tableBody = document.getElementById('sectionsTableBody');
    const modalTitle = document.getElementById('modalTitle');
    const sectionId = document.getElementById('sectionId');
    const nameInput = document.getElementById('name');
    const blockIdInput = document.getElementById('block_id');
    const descInput = document.getElementById('description');
    
    // Spatial fields
    const mapXInput = document.getElementById('map_x');
    const mapYInput = document.getElementById('map_y');
    const mapWidthInput = document.getElementById('map_width');
    const mapHeightInput = document.getElementById('map_height');
    const sectionMapPreview = document.getElementById('sectionMapPreview');
    const previewContent = document.getElementById('previewContent');
    const coordinatesInfo = document.getElementById('coordinatesInfo');

    // Filter Elements
    const filterBtn = document.getElementById('filterBtn');
    const filterPopover = document.getElementById('filterPopover');
    const filterBadge = document.getElementById('filterBadge');
    const activeFiltersRow = document.getElementById('activeFiltersRow');
    const sectionSearch = document.getElementById('sectionSearch');
    const clearAllBtn = document.getElementById('clearAllFilters');
    const addSectionBtn = document.getElementById('addSectionBtn');
    
    // Filter Inputs
    const blockCheckboxes = document.querySelectorAll('input[name="block_filter"]');
    const lotMinInput = document.getElementById('lotMin');
    const lotMaxInput = document.getElementById('lotMax');
    const dateRangeInput = document.getElementById('dateRange');
    const sortBySelect = document.getElementById('sortBy');
    const sortOrderRadios = document.querySelectorAll('input[name="sortOrder"]');

    let datePicker = null;

    // Map Picker Variables
    const mapPickerOverlay = document.getElementById('mapPickerOverlay');
    const mapPickerWrapper = document.getElementById('mapPickerWrapper');
    const mapPickerCanvas = document.getElementById('mapPickerCanvas');
    const mapPickerImage = document.getElementById('mapPickerImage');
    const selectionRect = document.getElementById('selectionRect');

    let pickerZoom = 1;
    let pickerPanX = 0;
    let pickerPanY = 0;
    let isDrawing = false;
    let isPanning = false;
    let startPanX, startPanY;
    let startX, startY;
    let currentRect = null;
    let currentTool = 'draw';

    // Initialize Flatpickr
    if (dateRangeInput) {
        datePicker = flatpickr(dateRangeInput, {
            mode: "range",
            dateFormat: "Y-m-d",
            onClose: function(selectedDates, dateStr, instance) {
                if (selectedDates.length === 2) {
                    loadSections();
                }
            }
        });
    }

    // Map Picker Tool Selection
    window.setPickerTool = (tool) => {
        currentTool = tool;
        document.getElementById('pickerDrawBtn').classList.toggle('active', tool === 'draw');
        document.getElementById('pickerPanBtn').classList.toggle('active', tool === 'pan');
        
        if (tool === 'draw') {
            mapPickerWrapper.classList.add('crosshair');
            mapPickerWrapper.classList.remove('grabbing');
        } else {
            mapPickerWrapper.classList.remove('crosshair');
        }
    };

    // Initialize Map Picker on image load
    mapPickerImage.onload = () => {
        resetPickerView();
    };

    window.resetPickerView = () => {
        const containerWidth = mapPickerWrapper.clientWidth;
        const containerHeight = mapPickerWrapper.clientHeight;
        const imgWidth = mapPickerImage.naturalWidth;
        const imgHeight = mapPickerImage.naturalHeight;

        if (imgWidth === 0) return; // Not loaded yet

        // Calculate zoom to fit image
        const scaleX = containerWidth / imgWidth;
        const scaleY = containerHeight / imgHeight;
        pickerZoom = Math.min(scaleX, scaleY, 1);

        // Center image
        pickerPanX = (containerWidth - imgWidth * pickerZoom) / 2;
        pickerPanY = (containerHeight - imgHeight * pickerZoom) / 2;

        updatePickerTransform();
    };

    window.openMapPicker = () => {
        modal.style.display = 'none';
        mapPickerOverlay.style.display = 'flex';
        
        // Remove any previously rendered existing areas
        document.querySelectorAll('.existing-area-rect').forEach(el => el.remove());

        // Use a small timeout to ensure container dimensions are calculated
        setTimeout(() => {
            if (mapPickerImage.complete) {
                resetPickerView();
            }
            
            // Render other existing sections to avoid overlap
            if (window.existingSectionAreas) {
                const currentEditingId = sectionId.value;
                window.existingSectionAreas.forEach(area => {
                    // Don't show the one we are currently editing as a background area
                    if (area.id == currentEditingId) return;

                    const rect = document.createElement('div');
                    rect.className = 'existing-area-rect';
                    rect.style.position = 'absolute';
                    rect.style.left = area.map_x + '%';
                    rect.style.top = area.map_y + '%';
                    rect.style.width = area.map_width + '%';
                    rect.style.height = area.map_height + '%';
                    rect.style.border = '2px solid rgba(59, 130, 246, 0.8)';
                    rect.style.background = 'rgba(59, 130, 246, 0.4)'; // Increased opacity for better visibility
                    rect.style.pointerEvents = 'none';
                    rect.style.zIndex = '150'; // Increased z-index to show above map image
                    
                    // Add label
                    const label = document.createElement('div');
                    label.textContent = area.name;
                    label.style.position = 'absolute';
                    label.style.top = '2px';
                    label.style.left = '2px';
                    label.style.fontSize = '12px'; // Increased font size for visibility
                    label.style.background = 'rgba(59, 130, 246, 0.9)';
                    label.style.color = 'white';
                    label.style.padding = '2px 6px';
                    label.style.borderRadius = '4px';
                    label.style.fontWeight = '700';
                    rect.appendChild(label);

                    mapPickerCanvas.appendChild(rect);
                });
            }

            // If existing area, show it
            if (mapXInput.value && mapYInput.value) {
                const x = parseFloat(mapXInput.value);
                const y = parseFloat(mapYInput.value);
                const w = parseFloat(mapWidthInput.value);
                const h = parseFloat(mapHeightInput.value);
                
                showSelectionRect(x, y, w, h);
                currentRect = { x, y, w, h };
            } else {
                selectionRect.style.display = 'none';
                currentRect = null;
            }
            setPickerTool('draw');
        }, 50);
    };

    window.closeMapPicker = () => {
        mapPickerOverlay.style.display = 'none';
        modal.style.display = 'flex';
    };

    window.saveMapArea = () => {
        if (currentRect) {
            mapXInput.value = currentRect.x.toFixed(4);
            mapYInput.value = currentRect.y.toFixed(4);
            mapWidthInput.value = currentRect.w.toFixed(4);
            mapHeightInput.value = currentRect.h.toFixed(4);
            updateSpatialPreview();
            closeMapPicker();
        } else {
            showNotification('Please draw an area on the map first', 'warning');
        }
    };

    window.zoomMap = (factor) => {
        // Zoom relative to center of wrapper
        const containerWidth = mapPickerWrapper.clientWidth;
        const containerHeight = mapPickerWrapper.clientHeight;
        
        const oldZoom = pickerZoom;
        pickerZoom *= factor;
        pickerZoom = Math.min(5, Math.max(0.05, pickerZoom));
        
        // Adjust pan to zoom into center
        const zoomRatio = pickerZoom / oldZoom;
        pickerPanX = (containerWidth / 2) - ((containerWidth / 2 - pickerPanX) * zoomRatio);
        pickerPanY = (containerHeight / 2) - ((containerHeight / 2 - pickerPanY) * zoomRatio);
        
        updatePickerTransform();
    };

    function updatePickerTransform() {
        mapPickerCanvas.style.transform = `translate(${pickerPanX}px, ${pickerPanY}px) scale(${pickerZoom})`;
    }

    function showSelectionRect(xPercent, yPercent, wPercent, hPercent) {
        const imgWidth = mapPickerImage.naturalWidth;
        const imgHeight = mapPickerImage.naturalHeight;
        
        selectionRect.style.left = (xPercent * imgWidth / 100) + 'px';
        selectionRect.style.top = (yPercent * imgHeight / 100) + 'px';
        selectionRect.style.width = (wPercent * imgWidth / 100) + 'px';
        selectionRect.style.height = (hPercent * imgHeight / 100) + 'px';
        selectionRect.style.display = 'block';
    }

    // Map Picker Interaction
    mapPickerWrapper.addEventListener('mousedown', (e) => {
        if (e.button !== 0) return;
        
        const rect = mapPickerWrapper.getBoundingClientRect();
        const x = (e.clientX - rect.left - pickerPanX) / pickerZoom;
        const y = (e.clientY - rect.top - pickerPanY) / pickerZoom;

        if (currentTool === 'pan') {
            isPanning = true;
            startPanX = e.clientX - pickerPanX;
            startPanY = e.clientY - pickerPanY;
            mapPickerWrapper.classList.add('grabbing');
        } else if (currentTool === 'draw') {
            isDrawing = true;
            startX = x;
            startY = y;
            
            selectionRect.style.display = 'block';
            selectionRect.style.left = x + 'px';
            selectionRect.style.top = y + 'px';
            selectionRect.style.width = '0px';
            selectionRect.style.height = '0px';
        }
    });

    window.addEventListener('mousemove', (e) => {
        if (isPanning) {
            pickerPanX = e.clientX - startPanX;
            pickerPanY = e.clientY - startPanY;
            updatePickerTransform();
        } else if (isDrawing) {
            const rect = mapPickerWrapper.getBoundingClientRect();
            const x = (e.clientX - rect.left - pickerPanX) / pickerZoom;
            const y = (e.clientY - rect.top - pickerPanY) / pickerZoom;
            
            const width = Math.abs(x - startX);
            const height = Math.abs(y - startY);
            const left = Math.min(x, startX);
            const top = Math.min(y, startY);
            
            selectionRect.style.left = left + 'px';
            selectionRect.style.top = top + 'px';
            selectionRect.style.width = width + 'px';
            selectionRect.style.height = height + 'px';
            
            // Store as percentages of natural dimensions
            const imgWidth = mapPickerImage.naturalWidth;
            const imgHeight = mapPickerImage.naturalHeight;
            
            currentRect = {
                x: (left / imgWidth) * 100,
                y: (top / imgHeight) * 100,
                w: (width / imgWidth) * 100,
                h: (height / imgHeight) * 100
            };
        }
    });

    window.addEventListener('mouseup', () => {
        isDrawing = false;
        isPanning = false;
        mapPickerWrapper.classList.remove('grabbing');
    });

    // Filter State
    let filters = {
        search: '',
        blocks: [],
        lotMin: '',
        lotMax: '',
        startDate: '',
        endDate: '',
        sortBy: 'name',
        sortOrder: 'ASC'
    };

    // Toggle Filter Popover
    if (filterBtn) {
        filterBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            filterPopover.classList.toggle('active');
        });

        // Close popover when clicking outside
        document.addEventListener('click', (e) => {
            if (filterPopover && !filterPopover.contains(e.target) && e.target !== filterBtn) {
                filterPopover.classList.remove('active');
            }
        });
    }

    // Category Toggles in Popover
    const categories = document.querySelectorAll('.filter-category');
    categories.forEach(cat => {
        cat.addEventListener('click', () => {
            categories.forEach(c => c.classList.remove('active'));
            cat.classList.add('active');
            
            // Hide all content, show selected
            document.querySelectorAll('.category-content').forEach(content => {
                content.style.display = 'none';
            });
            const catId = cat.getAttribute('data-category');
            document.getElementById(`cat-${catId}`).style.display = 'block';
        });
    });

    // Load Sections with Filters
    async function loadSections() {
        // Build query params
        const params = new URLSearchParams();
        if (filters.search) params.append('search', filters.search);
        if (filters.blocks.length > 0) params.append('block_id', filters.blocks.join(','));
        if (filters.lotMin) params.append('lot_min', filters.lotMin);
        if (filters.lotMax) params.append('lot_max', filters.lotMax);
        if (filters.startDate) params.append('start_date', filters.startDate);
        if (filters.endDate) params.append('end_date', filters.endDate);
        params.append('sort_by', filters.sortBy);
        params.append('sort_order', filters.sortOrder);

        try {
            const response = await fetch(`../api/sections.php?${params.toString()}`);
            if (!response.ok) throw new Error('Failed to fetch sections');
            
            const sections = await response.json();
            renderTable(sections);
            updateFilterUI();
        } catch (error) {
            console.error('Error loading sections:', error);
            showNotification('Failed to load sections', 'error');
        }
    }

    // Render Table Body
    function renderTable(sections) {
        if (!tableBody) return;
        
        if (sections.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="6" style="text-align: center; padding: 60px; color: #94a3b8;">
                        <div style="margin-bottom: 12px; opacity: 0.5;">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/></svg>
                        </div>
                        No sections found matching your filters.
                    </td>
                </tr>
            `;
            return;
        }

        tableBody.innerHTML = sections.map(section => {
            const sectionJson = JSON.stringify(section).replace(/'/g, "&apos;");
            const hasArea = section.map_x !== null && section.map_y !== null;
            
            let mapButton = '';
            if (hasArea) {
                mapButton = `
                    <a href="cemetery-map.php?highlight_section=${section.id}" class="btn-action btn-map" title="View on Map" style="background: #eff6ff; color: #3b82f6; display: flex; align-items: center; justify-content: center; text-decoration: none;">
                        <span class="icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                <circle cx="12" cy="10" r="3" />
                            </svg>
                        </span>
                    </a>
                `;
            }

            return `
            <tr>
                <td>
                    <div class="section-name-cell">
                        <div class="section-icon ${hasArea ? 'has-area' : ''}" title="${hasArea ? 'Area defined on map' : 'No area defined'}">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16" /><path d="M4 12h16" /><path d="M4 17h16" /></svg>
                            ${hasArea ? '<div class="area-indicator"></div>' : ''}
                        </div>
                        <div class="section-info">
                            <span class="name">${escapeHtml(section.name)}</span>
                            <span class="sub">ID: #${section.id}</span>
                        </div>
                    </div>
                </td>
                <td>
                    ${section.block_name ? `<span style="color: #1e293b; font-weight: 500;">${escapeHtml(section.block_name)}</span>` : `<span style="color: #94a3b8; font-style: italic;">No Block</span>`}
                </td>
                <td>${escapeHtml(section.description || 'No description provided')}</td>
                <td align="center">
                    <span style="background: #eff6ff; color: #3b82f6; padding: 4px 12px; border-radius: 20px; font-weight: 600; font-size: 12px;">
                        ${section.lot_count} Lots
                    </span>
                </td>
                <td>${formatDate(section.created_at)}</td>
                <td align="right">
                    <div style="display: flex; justify-content: flex-end; gap: 8px;">
                        ${mapButton}
                        <button class="btn-action btn-edit" onclick='openEditModal(${sectionJson})' title="Edit Section">
                            <span class="icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 20h9" />
                                    <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
                                </svg>
                            </span>
                        </button>
                        <button class="btn-action btn-delete" onclick='deleteSection(${sectionJson})' title="Delete Section">
                            <span class="icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="21 8 21 21 3 21 3 8"></polyline><rect x="1" y="3" width="22" height="5"></rect><line x1="10" y1="12" x2="14" y2="12"></line>
                                </svg>
                            </span>
                        </button>
                    </div>
                </td>
            </tr>
        `}).join('');
    }

    // Update Filter Badge and Chips
    function updateFilterUI() {
        let count = 0;
        activeFiltersRow.innerHTML = '';

        // Search Chip
        if (filters.search) {
            addFilterChip('Search: ' + filters.search, () => {
                filters.search = '';
                sectionSearch.value = '';
                loadSections();
            });
            count++;
        }

        // Block Chips
        filters.blocks.forEach(blockId => {
            const checkbox = document.querySelector(`input[name="block_filter"][value="${blockId}"]`);
            const name = checkbox ? checkbox.getAttribute('data-name') : 'Block ' + blockId;
            addFilterChip('Block: ' + name, () => {
                filters.blocks = filters.blocks.filter(id => id !== blockId);
                if (checkbox) checkbox.checked = false;
                loadSections();
            });
            count++;
        });

        // Lot Range Chip
        if (filters.lotMin || filters.lotMax) {
            const label = `Lots: ${filters.lotMin || 0} - ${filters.lotMax || 'Any'}`;
            addFilterChip(label, () => {
                filters.lotMin = '';
                filters.lotMax = '';
                lotMinInput.value = '';
                lotMaxInput.value = '';
                loadSections();
            });
            count++;
        }

        // Date Range Chip
        if (filters.startDate) {
            const label = `Date: ${filters.startDate} to ${filters.endDate}`;
            addFilterChip(label, () => {
                filters.startDate = '';
                filters.endDate = '';
                datePicker.clear();
                loadSections();
            });
            count++;
        }

        // Update Badge
        if (count > 0) {
            filterBadge.innerText = count;
            filterBadge.style.display = 'flex';
        } else {
            filterBadge.style.display = 'none';
        }
    }

    function addFilterChip(text, onRemove) {
        const chip = document.createElement('div');
        chip.className = 'filter-chip';
        chip.innerHTML = `
            <span>${text}</span>
            <span class="remove">&times;</span>
        `;
        chip.querySelector('.remove').addEventListener('click', onRemove);
        activeFiltersRow.appendChild(chip);
    }

    // Event Listeners for Filters
    if (sectionSearch) {
        let searchTimeout;
        sectionSearch.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                filters.search = e.target.value;
                loadSections();
            }, 300);
        });
    }

    blockCheckboxes.forEach(cb => {
        cb.addEventListener('change', () => {
            filters.blocks = Array.from(blockCheckboxes)
                .filter(c => c.checked)
                .map(c => c.value);
            loadSections();
        });
    });

    [lotMinInput, lotMaxInput].forEach(input => {
        if (input) {
            input.addEventListener('change', () => {
                filters.lotMin = lotMinInput.value;
                filters.lotMax = lotMaxInput.value;
                loadSections();
            });
        }
    });

    if (sortBySelect) {
        sortBySelect.addEventListener('change', () => {
            filters.sortBy = sortBySelect.value;
            loadSections();
        });
    }

    sortOrderRadios.forEach(radio => {
        radio.addEventListener('change', () => {
            filters.sortOrder = radio.value;
            loadSections();
        });
    });

    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', () => {
            filters = {
                search: '',
                blocks: [],
                lotMin: '',
                lotMax: '',
                startDate: '',
                endDate: '',
                sortBy: 'name',
                sortOrder: 'ASC'
            };
            
            // Reset UI
            sectionSearch.value = '';
            blockCheckboxes.forEach(cb => cb.checked = false);
            lotMinInput.value = '';
            lotMaxInput.value = '';
            if (datePicker) datePicker.clear();
            sortBySelect.value = 'name';
            sortOrderRadios[0].checked = true;
            
            loadSections();
        });
    }

    if (addSectionBtn) {
        addSectionBtn.addEventListener('click', () => {
            openAddModal();
        });
    }

    // Modal Logic
    window.openAddModal = () => {
        if (!modal) return;
        modalTitle.innerText = 'Add New Section';
        sectionId.value = '';
        form.reset();
        resetSpatialFields();
        if (blockIdInput) blockIdInput.value = '';
        modal.style.display = 'flex';
    };

    window.openEditModal = (section) => {
        if (!modal) return;
        modalTitle.innerText = 'Edit Section';
        sectionId.value = section.id;
        nameInput.value = section.name;
        if (blockIdInput) blockIdInput.value = section.block_id || '';
        descInput.value = section.description || '';
        
        // Fill spatial fields
        mapXInput.value = section.map_x || '';
        mapYInput.value = section.map_y || '';
        mapWidthInput.value = section.map_width || '';
        mapHeightInput.value = section.map_height || '';
        
        updateSpatialPreview();
        modal.style.display = 'flex';
    };

    function resetSpatialFields() {
        mapXInput.value = '';
        mapYInput.value = '';
        mapWidthInput.value = '';
        mapHeightInput.value = '';
        updateSpatialPreview();
    }

    function updateSpatialPreview() {
        if (mapXInput.value && mapYInput.value && mapWidthInput.value && mapHeightInput.value) {
            const x = parseFloat(mapXInput.value);
            const y = parseFloat(mapYInput.value);
            const w = parseFloat(mapWidthInput.value);
            const h = parseFloat(mapHeightInput.value);

            // Calculate CSS background properties to show the crop
            // Formula for background-position when size is scaled: 
            // pos = offset / (scaled_size - container_size)
            // But since we use percentages for both, it's simpler:
            const bgSize = (100 / w * 100);
            const posX = (x / (100 - w) * 100);
            const posY = (y / (100 - h) * 100);

            previewContent.innerHTML = `
                <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; 
                            background-image: url('../assets/images/cemetery.jpg'); 
                            background-size: ${bgSize}% auto; 
                            background-position: ${posX}% ${posY}%; 
                            filter: brightness(0.7);">
                </div>
                <div style="position: relative; z-index: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; height: 100%; background: rgba(0,0,0,0.2);">
                    <div style="background: rgba(255,255,255,0.9); color: #3b82f6; padding: 8px; border-radius: 50%; margin-bottom: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    </div>
                    <span style="color: white; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.5); font-size: 14px;">Area Defined</span>
                    <span style="color: rgba(255,255,255,0.9); font-size: 11px; font-weight: 500;">Click to redefine</span>
                </div>
            `;
            sectionMapPreview.style.background = '#f0f9ff';
            sectionMapPreview.style.borderColor = '#3b82f6';
            sectionMapPreview.style.borderStyle = 'solid';
            coordinatesInfo.style.display = 'block';
        } else {
            previewContent.innerHTML = `
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6z"/><path d="M9 4v14"/><path d="M15 6v14"/></svg>
                <span>Draw Section Area on Map</span>
            `;
            sectionMapPreview.style.background = '#f8fafc';
            sectionMapPreview.style.borderColor = '#cbd5e1';
            sectionMapPreview.style.borderStyle = 'dashed';
            coordinatesInfo.style.display = 'none';
        }
    }

    window.closeModal = () => {
        if (modal) modal.style.display = 'none';
    };

    // Add wheel zoom support
    mapPickerWrapper.addEventListener('wheel', (e) => {
        e.preventDefault();
        const factor = e.deltaY > 0 ? 0.8 : 1.25;
        
        const rect = mapPickerWrapper.getBoundingClientRect();
        const mouseX = e.clientX - rect.left;
        const mouseY = e.clientY - rect.top;

        const oldZoom = pickerZoom;
        pickerZoom *= factor;
        pickerZoom = Math.min(5, Math.max(0.05, pickerZoom));
        
        const zoomRatio = pickerZoom / oldZoom;
        pickerPanX = mouseX - (mouseX - pickerPanX) * zoomRatio;
        pickerPanY = mouseY - (mouseY - pickerPanY) * zoomRatio;
        
        updatePickerTransform();
    }, { passive: false });

    const confirmModal = document.getElementById('confirmModal');
    const confirmMessage = document.getElementById('confirmMessage');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

    window.closeConfirmModal = () => {
        if (confirmModal) confirmModal.style.display = 'none';
    };

    window.onclick = (event) => {
        if (event.target == modal) closeModal();
        if (event.target == confirmModal) closeConfirmModal();
    };

    // Form Submission
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const data = {
            id: sectionId.value,
            name: nameInput.value,
            block_id: blockIdInput ? blockIdInput.value : null,
            description: descInput.value,
            map_x: mapXInput.value || null,
            map_y: mapYInput.value || null,
            map_width: mapWidthInput.value || null,
            map_height: mapHeightInput.value || null
        };

        const method = sectionId.value ? 'PUT' : 'POST';
        
        try {
            const response = await fetch('../api/sections.php', {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            if (response.ok) {
                closeModal();
                showNotification(sectionId.value ? 'Section updated successfully!' : 'Section added successfully!', 'success');
                setTimeout(() => loadSections(), 1000);
            } else {
                const result = await response.json();
                showNotification(result.error || 'Something went wrong', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('An error occurred. Please try again.', 'error');
        }
    });

    // Delete Section
    window.deleteSection = async (section) => {
        const lotCount = parseInt(section.lot_count) || 0;
        if (lotCount > 0) {
            showNotification(`Cannot delete Section '${section.name}' because it contains ${lotCount} lot(s).`, 'warning');
            return;
        }

        confirmMessage.innerText = `Are you sure you want to delete Section '${section.name}'? This action cannot be undone.`;
        confirmModal.style.display = 'flex';

        confirmDeleteBtn.onclick = async () => {
            closeConfirmModal();
            try {
                const response = await fetch(`../api/sections.php?id=${section.id}`, { method: 'DELETE' });
                if (response.ok) {
                    showNotification('Section deleted successfully!', 'success');
                    setTimeout(() => loadSections(), 1000);
                } else {
                    const result = await response.json();
                    showNotification(result.error || 'Something went wrong', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('An error occurred. Please try again.', 'error');
            }
        };
    };

    // Helper Functions
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        const iconMap = { success: '✓', error: '✕', warning: '!', info: 'i' };
        const titleMap = { success: 'Success', error: 'Error', warning: 'Warning', info: 'Info' };

        notification.innerHTML = `
            <div class="notification-icon">${iconMap[type]}</div>
            <div class="notification-content">
                <div class="notification-title">${titleMap[type]}</div>
                <div class="notification-message">${message}</div>
            </div>
            ${type === 'error' ? '<button class="notification-close" onclick="this.parentElement.remove()">&times;</button>' : ''}
        `;
        document.body.appendChild(notification);
        setTimeout(() => notification.classList.add('show'), 10);
        if (type !== 'error') {
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 400);
            }, 4000);
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        if (!dateStr) return 'N/A';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
    }

    // Initial Load
    loadSections();
});
