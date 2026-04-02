document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('blockModal');
    const form = document.getElementById('blockForm');
    const modalTitle = document.getElementById('modalTitle');
    const blockIdInput = document.getElementById('blockId');
    const nameInput = document.getElementById('name');
    const descInput = document.getElementById('description');

    // Spatial fields
    const mapXInput = document.getElementById('map_x');
    const mapYInput = document.getElementById('map_y');
    const mapWidthInput = document.getElementById('map_width');
    const mapHeightInput = document.getElementById('map_height');
    const blockMapPreview = document.getElementById('blockMapPreview');
    const previewContent = document.getElementById('previewContent');
    const coordinatesInfo = document.getElementById('coordinatesInfo');

    // Map Picker Elements
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

        if (imgWidth === 0) return;

        const scaleX = containerWidth / imgWidth;
        const scaleY = containerHeight / imgHeight;
        pickerZoom = Math.min(scaleX, scaleY, 1);

        pickerPanX = (containerWidth - imgWidth * pickerZoom) / 2;
        pickerPanY = (containerHeight - imgHeight * pickerZoom) / 2;

        updatePickerTransform();
    };

    function updatePickerTransform() {
        mapPickerCanvas.style.transform = `translate(${pickerPanX}px, ${pickerPanY}px) scale(${pickerZoom})`;
    }

    window.zoomMap = (factor) => {
        const containerWidth = mapPickerWrapper.clientWidth;
        const containerHeight = mapPickerWrapper.clientHeight;
        
        const oldZoom = pickerZoom;
        pickerZoom *= factor;
        pickerZoom = Math.min(5, Math.max(0.05, pickerZoom));
        
        const zoomRatio = pickerZoom / oldZoom;
        pickerPanX = (containerWidth / 2) - ((containerWidth / 2 - pickerPanX) * zoomRatio);
        pickerPanY = (containerHeight / 2) - ((containerHeight / 2 - pickerPanY) * zoomRatio);
        
        updatePickerTransform();
    };

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

    window.openMapPicker = () => {
        modal.style.display = 'none';
        mapPickerOverlay.style.display = 'flex';
        
        // Remove any previously rendered existing areas
        document.querySelectorAll('.existing-area-rect').forEach(el => el.remove());

        setTimeout(() => {
            if (mapPickerImage.complete) resetPickerView();

            // Render other existing blocks to avoid overlap
            if (window.existingBlockAreas) {
                const currentEditingId = blockIdInput.value;
                window.existingBlockAreas.forEach(area => {
                    // Don't show the one we are currently editing as a background area
                    if (area.id == currentEditingId) return;

                    const rect = document.createElement('div');
                    rect.className = 'existing-area-rect';
                    rect.style.position = 'absolute';
                    rect.style.left = area.map_x + '%';
                    rect.style.top = area.map_y + '%';
                    rect.style.width = area.map_width + '%';
                    rect.style.height = area.map_height + '%';
                    rect.style.border = '2px solid rgba(16, 185, 129, 0.8)';
                    rect.style.background = 'rgba(16, 185, 129, 0.4)'; // Increased opacity for better visibility
                    rect.style.pointerEvents = 'none';
                    rect.style.zIndex = '150'; // Increased z-index
                    
                    // Add label
                    const label = document.createElement('div');
                    label.textContent = area.name;
                    label.style.position = 'absolute';
                    label.style.top = '2px';
                    label.style.left = '2px';
                    label.style.fontSize = '12px'; // Increased font size
                    label.style.background = 'rgba(16, 185, 129, 0.9)';
                    label.style.color = 'white';
                    label.style.padding = '2px 6px';
                    label.style.borderRadius = '4px';
                    label.style.fontWeight = '700';
                    rect.appendChild(label);

                    mapPickerCanvas.appendChild(rect);
                });
            }

            if (mapXInput.value && mapYInput.value) {
                showSelectionRect(parseFloat(mapXInput.value), parseFloat(mapYInput.value), parseFloat(mapWidthInput.value), parseFloat(mapHeightInput.value));
                currentRect = { x: parseFloat(mapXInput.value), y: parseFloat(mapYInput.value), w: parseFloat(mapWidthInput.value), h: parseFloat(mapHeightInput.value) };
            } else {
                selectionRect.style.display = 'none';
                currentRect = null;
            }
            setPickerTool('draw');
        }, 50);
    };

    function showSelectionRect(xPercent, yPercent, wPercent, hPercent) {
        const imgWidth = mapPickerImage.naturalWidth;
        const imgHeight = mapPickerImage.naturalHeight;
        selectionRect.style.left = (xPercent * imgWidth / 100) + 'px';
        selectionRect.style.top = (yPercent * imgHeight / 100) + 'px';
        selectionRect.style.width = (wPercent * imgWidth / 100) + 'px';
        selectionRect.style.height = (hPercent * imgHeight / 100) + 'px';
        selectionRect.style.display = 'block';
    }

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

    window.closeMapPicker = () => {
        mapPickerOverlay.style.display = 'none';
        modal.style.display = 'flex';
    };

    function updateSpatialPreview() {
        if (mapXInput.value && mapYInput.value && mapWidthInput.value && mapHeightInput.value) {
            const x = parseFloat(mapXInput.value);
            const y = parseFloat(mapYInput.value);
            const w = parseFloat(mapWidthInput.value);
            const h = parseFloat(mapHeightInput.value);

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
                    <div style="background: rgba(255,255,255,0.9); color: #10b981; padding: 8px; border-radius: 50%; margin-bottom: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    </div>
                    <span style="color: white; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.5); font-size: 14px;">Area Defined</span>
                    <span style="color: rgba(255,255,255,0.9); font-size: 11px; font-weight: 500;">Click to redefine</span>
                </div>
            `;
            blockMapPreview.style.background = '#f0fdf4';
            blockMapPreview.style.borderColor = '#10b981';
            blockMapPreview.style.borderStyle = 'solid';
            coordinatesInfo.style.display = 'block';
        } else {
            previewContent.innerHTML = `
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6z"/><path d="M9 4v14"/><path d="M15 6v14"/></svg>
                <span>Draw Block Area on Map</span>
            `;
            blockMapPreview.style.background = '#f8fafc';
            blockMapPreview.style.borderColor = '#cbd5e1';
            blockMapPreview.style.borderStyle = 'dashed';
            coordinatesInfo.style.display = 'none';
        }
    }

    // Open Modal for Add
    window.openAddModal = () => {
        modalTitle.innerText = 'Add New Block';
        blockIdInput.value = '';
        form.reset();
        resetSpatialFields();
        modal.style.display = 'flex';
    };

    // Open Modal for Edit
    window.openEditModal = (block) => {
        modalTitle.innerText = 'Edit Block';
        blockIdInput.value = block.id;
        nameInput.value = block.name;
        descInput.value = block.description || '';
        
        mapXInput.value = block.map_x || '';
        mapYInput.value = block.map_y || '';
        mapWidthInput.value = block.map_width || '';
        mapHeightInput.value = block.map_height || '';
        
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

    // Close Modal
    window.closeModal = () => {
        modal.style.display = 'none';
    };

    // Confirmation Modal
    const confirmModal = document.getElementById('confirmModal');
    const confirmMessage = document.getElementById('confirmMessage');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

    window.closeConfirmModal = () => {
        confirmModal.style.display = 'none';
    };

    window.onclick = (event) => {
        if (event.target == modal) closeModal();
        if (event.target == confirmModal) closeConfirmModal();
    };

    // Form Submission
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const id = blockIdInput.value;
        const data = {
            id: id,
            name: nameInput.value,
            description: descInput.value,
            map_x: mapXInput.value || null,
            map_y: mapYInput.value || null,
            map_width: mapWidthInput.value || null,
            map_height: mapHeightInput.value || null
        };

        try {
            let result;
            if (id) {
                result = await API.updateBlock(id, data);
            } else {
                result = await API.createBlock(data);
            }

            if (result.success) {
                closeModal();
                showNotification(id ? 'Block updated successfully!' : 'Block added successfully!', 'success');
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showNotification(result.message || 'Something went wrong', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('An error occurred. Please try again.', 'error');
        }
    });

    // Delete Block
    window.deleteBlock = async (block) => {
        const lotCount = parseInt(block.lot_count) || 0;
        if (lotCount > 0) {
            showNotification(`Cannot delete Block '${block.name}' because it contains ${lotCount} lot(s).`, 'warning');
            return;
        }

        confirmMessage.innerText = `Are you sure you want to delete Block '${block.name}'? This action cannot be undone.`;
        confirmModal.style.display = 'flex';

        confirmDeleteBtn.onclick = async () => {
            closeConfirmModal();
            try {
                const result = await API.deleteBlock(block.id);
                if (result.success) {
                    showNotification('Block deleted successfully!', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification(result.message || 'Something went wrong', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('An error occurred. Please try again.', 'error');
            }
        };
    };

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
});
