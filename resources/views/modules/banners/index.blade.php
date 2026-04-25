@extends('layouts.app')

@section('title', 'Banner Management')

@section('content')
<style>
    .cursor-pointer { cursor: pointer; }
    .smaller { font-size: 0.75rem; }
</style>
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">System /</span> Banners
</h4>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Banner Positions</h5>
        <small class="text-muted">Positions are fixed and cannot be added or deleted.</small>
    </div>
    <div class="card-body">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Position</th>
                        <th>Type</th>
                        <th>Preview</th>
                        <th>Title</th>
                        <th>Link</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push('modals')
<!-- Preview Modal -->
<div class="modal fade" id="previewBannerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Banner Preview: <span id="previewPositionName" class="text-primary"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="previewContainer" class="row g-3">
                    <!-- Images will be injected here -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Banner Modal -->
<div class="modal fade" id="editBannerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Banner: <span id="modalPositionName" class="text-primary"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editBannerForm" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Banner Type</label>
                            <select name="type" id="bannerType" class="form-select" required>
                                <option value="single">Single Image</option>
                                <option value="carousel">Carousel (Multiple Images)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="bannerActive" name="is_active" checked>
                                <label class="form-check-label" for="bannerActive">Active</label>
                            </div>
                        </div>
                    </div>

                    <!-- Single Type Fields -->
                    <div id="singleFields">
                        <hr>
                        <div class="mb-3">
                            <label class="form-label">Banner Image</label>
                            <div class="d-flex align-items-start align-items-sm-center gap-4">
                                <img src="{{ asset('assets/img/no_image.png') }}" alt="banner-image" class="d-block rounded" height="100" id="uploadedBanner" />
                                <div class="button-wrapper">
                                    <label for="upload" class="btn btn-primary me-2 mb-2" tabindex="0">
                                        <span class="d-none d-sm-block">Upload photo</span>
                                        <i class="bx bx-upload d-block d-sm-none"></i>
                                        <input type="file" id="upload" name="image" class="account-file-input" hidden accept="image/png, image/jpeg, image/webp" />
                                    </label>
                                    <p class="text-muted mb-0 small">Allowed JPG, PNG or WEBP. Max 2MB</p>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="bannerTitle">Title</label>
                            <input type="text" class="form-control" id="bannerTitle" name="title" placeholder="Enter banner title" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="bannerLink">Link</label>
                            <input type="url" class="form-control" id="bannerLink" name="link" placeholder="https://example.com" />
                        </div>
                    </div>

                    <!-- Carousel Type Fields -->
                    <div id="carouselFields" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Carousel Items</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="addCarouselItem">
                                <i class="bx bx-plus me-1"></i> Add Item
                            </button>
                        </div>
                        <div id="carouselItemsContainer">
                            <!-- Carousel items will be injected here -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endpush

@push('page-js')
<script type="text/javascript">
  document.addEventListener('DOMContentLoaded', function () {
    const table = $('.data-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('banners.index') }}",
        columns: [
            {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'position', name: 'position'},
            {data: 'type', name: 'type', render: function(data) {
                return '<span class="badge bg-label-info">' + data.toUpperCase() + '</span>';
            }},
            {data: 'image', name: 'image', orderable: false, searchable: false},
            {data: 'title', name: 'title'},
            {data: 'link', name: 'link'},
            {data: 'is_active', name: 'is_active'},
            {data: 'action', name: 'action', orderable: false, searchable: false},
        ]
    });

    const editModal = new bootstrap.Modal(document.getElementById('editBannerModal'));
    const previewModal = new bootstrap.Modal(document.getElementById('previewBannerModal'));
    let currentPosition = '';
    const carouselContainer = $('#carouselItemsContainer');

    $(document).on('click', '.preview-trigger', function() {
        const row = table.row($(this).closest('tr')).data();
        const position = $(this).data('position');
        const type = row.type;
        
        $('#previewPositionName').text(position.replace('_', ' ').toUpperCase());
        const container = $('#previewContainer');
        container.empty();

        if (type === 'single') {
            const imageUrl = row.image_url;
            container.append(`
                <div class="col-12">
                    <div class="card shadow-none border">
                        <img src="${imageUrl}" class="card-img-top rounded" style="max-height: 400px; object-fit: contain; background: #f8f9fa;">
                        <div class="card-body">
                            <h6 class="card-title mb-1">${row.title || 'No Title'}</h6>
                            <p class="card-text small text-muted text-truncate mb-0">${row.link || 'No Link'}</p>
                        </div>
                    </div>
                </div>
            `);
        } else {
            const items = row.items || [];
            if (items.length === 0) {
                container.append('<div class="col-12 text-center py-4 text-muted">No images found in carousel</div>');
            } else {
                items.forEach(item => {
                    const imageUrl = item.image_url;
                    container.append(`
                        <div class="col-md-6">
                            <div class="card shadow-none border h-100">
                                <img src="${imageUrl}" class="card-img-top rounded-top" style="height: 180px; object-fit: cover;">
                                <div class="card-body p-3">
                                    <h6 class="card-title mb-1 small fw-bold">${item.title || 'No Title'}</h6>
                                    <p class="card-text smaller text-muted text-truncate mb-0">${item.link || 'No Link'}</p>
                                </div>
                            </div>
                        </div>
                    `);
                });
            }
        }
        previewModal.show();
    });

    // Toggle fields based on type
    $('#bannerType').on('change', function() {
        if ($(this).val() === 'single') {
            $('#singleFields').show();
            $('#carouselFields').hide();
        } else {
            $('#singleFields').hide();
            $('#carouselFields').show();
            if (carouselContainer.children().length === 0) {
                addCarouselItem();
            }
        }
    });

    function addCarouselItem(data = null, index = null) {
        if (index === null) {
            index = carouselContainer.children().length;
        }
        
        const imageUrl = data && data.image_url ? data.image_url : '{{ asset("assets/img/no_image.png") }}';
        
        const itemHtml = `
            <div class="carousel-item-row border rounded p-3 mb-3 position-relative">
                <button type="button" class="btn-close position-absolute top-0 end-0 m-2 remove-item" aria-label="Close"></button>
                <div class="row">
                    <div class="col-md-3">
                        <div class="text-center mb-2">
                            <img src="${imageUrl}" class="rounded img-fluid item-preview" style="max-height: 80px;" />
                        </div>
                        <input type="file" name="items[${index}][image]" class="form-control form-control-sm carousel-file-input" accept="image/*" />
                    </div>
                    <div class="col-md-9">
                        <div class="mb-2">
                            <input type="text" name="items[${index}][title]" class="form-control form-control-sm" placeholder="Item Title" value="${data ? (data.title || '') : ''}" required />
                        </div>
                        <div>
                            <input type="url" name="items[${index}][link]" class="form-control form-control-sm" placeholder="Item Link" value="${data ? (data.link || '') : ''}" required />
                        </div>
                    </div>
                </div>
            </div>
        `;
        carouselContainer.append(itemHtml);
    }

    $(document).on('click', '#addCarouselItem', function() {
        addCarouselItem();
    });

    $(document).on('click', '.remove-item', function() {
        $(this).closest('.carousel-item-row').remove();
    });

    $(document).on('change', '.carousel-file-input', function() {
        const file = this.files[0];
        const preview = $(this).closest('.carousel-item-row').find('.item-preview');
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.attr('src', e.target.result);
            }
            reader.readAsDataURL(file);
        }
    });

    $(document).on('click', '.edit-banner-btn', function() {
        currentPosition = $(this).data('position');
        const type = $(this).data('type');
        const title = $(this).data('title');
        const link = $(this).data('link');
        const active = $(this).data('active');
        const image = $(this).data('image');
        const items = $(this).data('items');

        $('#modalPositionName').text(currentPosition.replace('_', ' ').toUpperCase());
        $('#bannerType').val(type).trigger('change');
        $('#bannerActive').prop('checked', active == 1);

        if (type === 'single') {
            $('#bannerTitle').val(title);
            $('#bannerLink').val(link);
            $('#uploadedBanner').attr('src', image ? image : '{{ asset("assets/img/no_image.png") }}');
        } else {
            carouselContainer.empty();
            if (items && items.length > 0) {
                items.forEach((item, idx) => addCarouselItem(item, idx));
            } else {
                addCarouselItem();
            }
        }

        editModal.show();
    });

    $(document).on('click', '.toggle-banner-status', function() {
        const position = $(this).data('position');
        const btn = $(this);
        
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status"></span>');

        $.ajax({
            url: "{{ url('banners') }}/" + position + "/toggle-status",
            type: 'POST',
            data: { _token: "{{ csrf_token() }}" },
            success: function(response) {
                if (response.status) {
                    table.ajax.reload();
                    showAlert(response.message, 'success', 'Success');
                } else {
                    showAlert(response.message || 'Something went wrong', 'error', 'Error');
                    table.ajax.reload();
                }
            },
            error: function() {
                showAlert('Failed to toggle banner status', 'error', 'Error');
                table.ajax.reload();
            }
        });
    });

    // Image preview for single
    $('#upload').on('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#uploadedBanner').attr('src', e.target.result);
            }
            reader.readAsDataURL(file);
        }
    });

    $('#editBannerForm').on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        const submitBtn = $(this).find('button[type="submit"]');
        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...');

        $.ajax({
            url: "{{ url('banners') }}/" + currentPosition,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.status) {
                    editModal.hide();
                    table.ajax.reload();
                    showAlert(response.message, 'success', 'Success');
                } else {
                    showAlert(response.message || 'Something went wrong', 'error', 'Error');
                }
            },
            error: function(xhr) {
                const errors = xhr.responseJSON.errors;
                if (errors) {
                    let errorMsg = '';
                    Object.keys(errors).forEach(key => {
                        errorMsg += errors[key][0] + '\n';
                    });
                    showAlert(errorMsg, 'error', 'Validation Error');
                } else {
                    showAlert('Failed to update banner', 'error', 'Error');
                }
            },
            complete: function() {
                submitBtn.prop('disabled', false).text('Save changes');
            }
        });
    });
  });
</script>
@endpush
