<?php

namespace App\Http\Controllers\MasterData;

/**
 * Loại tài liệu — mã loại là tiền tố của mã tài liệu sinh tự động (HDSD-MAY001-001);
 * từ khoá giúp tự nhận diện loại khi tải lên hàng loạt.
 */
class DocumentTypeController extends MasterDataController
{
    protected string $table       = 'document_types';
    protected string $routePrefix = 'document-types';
    protected string $title       = 'Loại tài liệu';
    protected string $singular    = 'loại tài liệu';
    protected string $description = 'Mã loại là tiền tố mã tài liệu. Từ khoá dùng để tự nhận diện loại tài liệu từ tên file khi tải lên.';
    protected string $icon        = 'file';
    protected string $orderBy     = 'sort_order';

    protected array $usages = ['documents' => 'document_type_id'];

    protected function fields(?object $record): array
    {
        return [
            'code'        => ['label' => 'Mã loại (tiền tố)', 'type' => 'text', 'required' => true, 'placeholder' => 'Vd: HDSD', 'span' => 1],
            'name'        => ['label' => 'Tên loại tài liệu', 'type' => 'text', 'required' => true, 'placeholder' => 'Vd: Hướng dẫn sử dụng', 'span' => 2],
            'color'       => ['label' => 'Màu nhận diện', 'type' => 'color', 'default' => '#3b82f6', 'span' => 1],
            'sort_order'  => ['label' => 'Thứ tự hiển thị', 'type' => 'number', 'default' => 0, 'span' => 2],
            'keywords'    => ['label' => 'Từ khoá nhận diện', 'type' => 'text', 'placeholder' => 'manual, hdsd, huong dan su dung', 'hint' => 'Cách nhau dấu phẩy, không phân biệt hoa thường / dấu. Tên file chứa từ khoá → tự chọn loại này.', 'span' => 3],
            'description' => ['label' => 'Mô tả', 'type' => 'textarea', 'span' => 3],
            'is_active'   => ['label' => 'Đang dùng', 'type' => 'checkbox'],
        ];
    }

    protected function columns(): array
    {
        return [
            'color'           => ['label' => '', 'type' => 'swatch'],
            'code'            => ['label' => 'Mã', 'type' => 'mono'],
            'name'            => ['label' => 'Tên loại tài liệu', 'type' => 'bold'],
            'keywords'        => ['label' => 'Từ khoá nhận diện', 'type' => 'text'],
            'usage_documents' => ['label' => 'Tài liệu', 'type' => 'count'],
            'is_active'       => ['label' => 'Trạng thái', 'type' => 'active'],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'code'        => ['required', 'string', 'max:20', 'alpha_dash', $this->unique('code', $id)],
            'name'        => ['required', 'string', 'max:255'],
            'color'       => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order'  => ['nullable', 'integer', 'min:0', 'max:9999'],
            'keywords'    => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
