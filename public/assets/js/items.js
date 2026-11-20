// View item details
(function () {
    var modal = document.getElementById('itemModal');
    if (!modal) return;

    var body    = document.getElementById('itemModalBody');
    var baseUrl = modal.dataset.url;
    var opener  = null;

    function openModal(id, btn) {
        opener = btn;
        body.innerHTML = '<p class="muted">Loading...</p>';
        modal.hidden = false;
        document.body.classList.add('modal-open');
        document.getElementById('closeItemModal').focus();

        fetch(baseUrl + '?id=' + encodeURIComponent(id), { credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
            .then(function (html) { body.innerHTML = html; })
            .catch(function () {
                body.innerHTML = '<p class="form-error">Could not load this item. Please try again.</p>';
            });
    }

    function closeModal() {
        modal.hidden = true;
        body.innerHTML = '';
        document.body.classList.remove('modal-open');
        if (opener) opener.focus();
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.view-detail');
        if (btn) { openModal(btn.dataset.itemId, btn); return; }
        if (e.target === modal || e.target.id === 'closeItemModal') closeModal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) closeModal();
    });
})();

// Confirm submit report + prevent double submit
var reportForm = document.querySelector('.report-form');
var confirmDialog = document.getElementById('confirm-dialog');
var openConfirm = document.getElementById('open-confirm');
var confirmSubmitBtn = document.getElementById('confirm-submit');
var confirmType = document.getElementById('confirm-type');

if (reportForm) {
    var hasDialog = confirmDialog && openConfirm && confirmSubmitBtn;
    var confirmed = false;
    var submitting = false;

    if (hasDialog) {
        openConfirm.addEventListener('click', function (event) {
            event.preventDefault();
            if (reportForm.reportValidity()) {
                // "lost" or "found" in "submit this ___ item report?"
                var typeField = reportForm.elements['item_type'];
                var typeValue = typeField ? String(typeField.value).toLowerCase() : '';

                if (confirmType && (typeValue === 'lost' || typeValue === 'found')) {
                    confirmType.textContent = typeValue;
                }
                confirmDialog.showModal();
            }
        });

        confirmSubmitBtn.addEventListener('click', function (event) {
            event.preventDefault();
            confirmed = true;
            confirmDialog.close();
            reportForm.requestSubmit();
        });
    }

    reportForm.addEventListener('submit', function (event) {
        if (submitting) {
            event.preventDefault();
            return;
        }

        if (hasDialog) {
            if (!confirmed) {
                event.preventDefault();
                if (reportForm.reportValidity() && !confirmDialog.open) {
                    confirmDialog.showModal();
                }
                return;
            }
        } else if (!confirm('Are you sure you want to submit this report?')) {
            event.preventDefault();
            return;
        }

        submitting = true;
        confirmed = false;

        var btn = reportForm.querySelector('button[type="submit"]') || openConfirm;
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Submitting...';
        }
    });
}

// preview report
(function () {
    var modal = document.getElementById('previewModal');
    if (!modal) return;

    var body = document.getElementById('previewModalBody');
    var previewBtn = document.getElementById('previewReport');
    var closeBtn = document.getElementById('closePreviewModal');
    var form = document.querySelector('.report-form');
    var photoUrl = null;

    if (!form || !previewBtn || !closeBtn || !body) return;

    function escapeHTML(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function openModal() {
        var formData = new FormData(form);

        var itemType = formData.get('item_type') || 'Lost';
        var itemName = formData.get('item_name') || 'Item name';
        var category = form.querySelector('#category_id');
        var location = form.querySelector('#location_id');
        var customLocation = formData.get('custom_location') || '';
        var eventDate = formData.get('event_date') || 'Not provided';
        var description = formData.get('description') || 'No description provided';

        var categoryName = category.options[category.selectedIndex].text;
        var locationName = location.options[location.selectedIndex].text;

        if (!category.value) categoryName = 'Not selected';
        if (!location.value) locationName = 'Not selected';

        if (customLocation.trim() !== '') {
            locationName += ' - ' + customLocation;
        }

        var photo = formData.get('photo');
        var photoHTML = '';

        if (photo && photo.size > 0) {
            if (photoUrl) URL.revokeObjectURL(photoUrl);

            photoUrl = URL.createObjectURL(photo);

            photoHTML = '<img src="' + photoUrl + '" alt="' +
                escapeHTML(itemName) + '" class="item-photo">';
        } else {
            photoHTML = `
                <div class="item-photo-empty">
                    <i class="fa-regular fa-image"></i>
                    <span>No photo attached</span>
                </div>
            `;
        }

        body.innerHTML = `
            <div class="item-modal-content">
                ${photoHTML}

                <div class="item-modal-info">

                    <div class="item-badges">
                        <span class="badge badge-${escapeHTML(String(itemType).toLowerCase())}">
                            ${escapeHTML(itemType)}
                        </span>
                        <span class="badge badge-own">Your report</span>
                    </div>

                    <h2>${escapeHTML(itemName)}</h2>

                    <p class="muted">${escapeHTML(categoryName)}</p>

                    <div class="item-meta">
                        <p>
                            <i class="fa-solid fa-location-dot"></i>
                            ${escapeHTML(locationName)}
                        </p>

                        <p>
                            <i class="fa-regular fa-calendar"></i>
                            ${escapeHTML(eventDate)}
                        </p>

                        <p>
                            <i class="fa-solid fa-user"></i>
                            Reported by you
                        </p>
                    </div>

                    <div class="item-description">
                        <h3>Description</h3>
                        <p>${escapeHTML(description).replace(/\n/g, '<br>')}</p>
                    </div>

                    <div class="item-modal-actions">
                        <button type="button" class="btn btn-primary" id="backToEdit">
                            Back to edit
                        </button>
                    </div>

                </div>
            </div>
        `;

        modal.hidden = false;
        document.body.classList.add('modal-open');
        closeBtn.focus();

        document.getElementById('backToEdit').addEventListener('click', closeModal);
    }

    function closeModal() {
        modal.hidden = true;
        body.innerHTML = '';

        document.body.classList.remove('modal-open');

        if (photoUrl) {
            URL.revokeObjectURL(photoUrl);
            photoUrl = null;
        }

        previewBtn.focus();
    }

    previewBtn.addEventListener('click', openModal);

    document.addEventListener('click', function (e) {
        if (e.target === modal || e.target.id === 'closePreviewModal') {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });

})();

// Require the specific location when "Other" is chosen
var locationSelect = document.getElementById('location_id');
var customLocationInput = document.getElementById('custom_location');

if (locationSelect && customLocationInput) {
    var defaultPlaceholder = customLocationInput.placeholder;

    var syncCustomLocation = function () {
        var opt = locationSelect.options[locationSelect.selectedIndex];
        var isOther = opt && opt.dataset.other === '1';

        customLocationInput.required = isOther;
        customLocationInput.placeholder = isOther
            ? 'Please specify the location (required)'
            : defaultPlaceholder;
    };

    locationSelect.addEventListener('change', syncCustomLocation);
    syncCustomLocation();
}