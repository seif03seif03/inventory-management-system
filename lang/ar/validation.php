<?php

/*
|--------------------------------------------------------------------------
| Arabic validation messages
|--------------------------------------------------------------------------
| Laravel 12 ships no translation files of its own, so without this file any
| rule that a controller does not override with a custom message would fall
| back to English even when the app is in Arabic.
|
| Only the rules this project actually uses are translated, plus the common
| ones a future form is likely to reach for. :attribute is replaced with the
| field name from the 'attributes' array at the bottom.
*/

return [

    'accepted'             => 'يجب قبول :attribute.',
    'active_url'           => ':attribute ليس رابطاً صحيحاً.',
    'after'                => 'يجب أن يكون :attribute تاريخاً بعد :date.',
    'after_or_equal'       => 'يجب أن يكون :attribute تاريخاً بعد أو يساوي :date.',
    'alpha'                => 'يجب أن يحتوي :attribute على حروف فقط.',
    'alpha_dash'           => 'يجب أن يحتوي :attribute على حروف وأرقام وشرطات فقط.',
    'alpha_num'            => 'يجب أن يحتوي :attribute على حروف وأرقام فقط.',
    'array'                => 'يجب أن يكون :attribute مصفوفة.',
    'before'               => 'يجب أن يكون :attribute تاريخاً قبل :date.',
    'before_or_equal'      => 'يجب أن يكون :attribute تاريخاً قبل أو يساوي :date.',
    'boolean'              => 'يجب أن تكون قيمة :attribute صحيحة أو خاطئة.',
    'confirmed'            => 'تأكيد :attribute غير مطابق.',
    'date'                 => ':attribute ليس تاريخاً صحيحاً.',
    'date_equals'          => 'يجب أن يكون :attribute تاريخاً مساوياً لـ :date.',
    'date_format'          => 'لا يطابق :attribute الصيغة :format.',
    'different'            => 'يجب أن يكون :attribute و :other مختلفين.',
    'digits'               => 'يجب أن يكون :attribute :digits رقماً.',
    'digits_between'       => 'يجب أن يكون :attribute بين :min و :max رقماً.',
    'email'                => 'يجب أن يكون :attribute بريداً إلكترونياً صحيحاً.',
    'exists'               => ':attribute المحدد غير موجود.',
    'filled'              => 'حقل :attribute مطلوب.',
    'gt'                   => [
        'numeric' => 'يجب أن يكون :attribute أكبر من :value.',
        'string'  => 'يجب أن يكون طول :attribute أكبر من :value حرفاً.',
        'array'   => 'يجب أن يحتوي :attribute على أكثر من :value عنصراً.',
    ],
    'gte'                  => [
        'numeric' => 'يجب أن يكون :attribute أكبر من أو يساوي :value.',
        'string'  => 'يجب أن يكون طول :attribute :value حرفاً أو أكثر.',
        'array'   => 'يجب أن يحتوي :attribute على :value عنصراً أو أكثر.',
    ],
    'image'                => 'يجب أن يكون :attribute صورة.',
    'in'                   => ':attribute المحدد غير صحيح.',
    'integer'              => 'يجب أن يكون :attribute عدداً صحيحاً.',
    'lt'                   => [
        'numeric' => 'يجب أن يكون :attribute أصغر من :value.',
        'string'  => 'يجب أن يكون طول :attribute أصغر من :value حرفاً.',
        'array'   => 'يجب أن يحتوي :attribute على أقل من :value عنصراً.',
    ],
    'lte'                  => [
        'numeric' => 'يجب أن يكون :attribute أصغر من أو يساوي :value.',
        'string'  => 'يجب ألا يزيد طول :attribute عن :value حرفاً.',
        'array'   => 'يجب ألا يحتوي :attribute على أكثر من :value عنصراً.',
    ],
    'max'                  => [
        'numeric' => 'يجب ألا يكون :attribute أكبر من :max.',
        'file'    => 'يجب ألا يكون حجم :attribute أكبر من :max كيلوبايت.',
        'string'  => 'يجب ألا يزيد طول :attribute عن :max حرفاً.',
        'array'   => 'يجب ألا يحتوي :attribute على أكثر من :max عنصراً.',
    ],
    'mimes'                => 'يجب أن يكون :attribute ملفاً من نوع: :values.',
    'mimetypes'            => 'يجب أن يكون :attribute ملفاً من نوع: :values.',
    'min'                  => [
        'numeric' => 'يجب أن يكون :attribute :min على الأقل.',
        'file'    => 'يجب أن يكون حجم :attribute :min كيلوبايت على الأقل.',
        'string'  => 'يجب أن يكون طول :attribute :min أحرف على الأقل.',
        'array'   => 'يجب أن يحتوي :attribute على :min عنصراً على الأقل.',
    ],
    'not_in'               => ':attribute المحدد غير صحيح.',
    'numeric'              => 'يجب أن يكون :attribute رقماً.',
    'present'              => 'يجب أن يكون حقل :attribute موجوداً.',
    'regex'                => 'صيغة :attribute غير صحيحة.',
    'required'             => 'حقل :attribute مطلوب.',
    'required_if'          => 'حقل :attribute مطلوب عندما يكون :other هو :value.',
    'required_unless'      => 'حقل :attribute مطلوب إلا إذا كان :other في :values.',
    'required_with'        => 'حقل :attribute مطلوب عند وجود :values.',
    'required_with_all'    => 'حقل :attribute مطلوب عند وجود :values.',
    'required_without'     => 'حقل :attribute مطلوب عند عدم وجود :values.',
    'required_without_all' => 'حقل :attribute مطلوب عند عدم وجود أي من :values.',
    'same'                 => 'يجب أن يتطابق :attribute مع :other.',
    'size'                 => [
        'numeric' => 'يجب أن يكون :attribute :size.',
        'file'    => 'يجب أن يكون حجم :attribute :size كيلوبايت.',
        'string'  => 'يجب أن يكون طول :attribute :size حرفاً.',
        'array'   => 'يجب أن يحتوي :attribute على :size عنصراً.',
    ],
    'string'               => 'يجب أن يكون :attribute نصاً.',
    'unique'               => ':attribute مستخدم بالفعل.',
    'uploaded'             => 'فشل تحميل :attribute.',
    'url'                  => 'صيغة :attribute غير صحيحة.',

    'custom' => [],

    /*
    | Field names, so a message reads "حقل المخزن مطلوب" rather than
    | "حقل warehouse_id مطلوب".
    */
    'attributes' => [
        'name'                  => 'الاسم',
        'email'                 => 'البريد الإلكتروني',
        'phone'                 => 'رقم الهاتف',
        'password'              => 'كلمة المرور',
        'current_password'      => 'كلمة المرور الحالية',
        'role_id'               => 'الدور',
        'receive_notifications' => 'استلام الإشعارات',
        'description'           => 'الوصف',
        'address'               => 'العنوان',
        'active'                => 'الحالة',
        'location'              => 'الموقع',
        'sku'                   => 'رمز SKU',
        'barcode'               => 'الباركود',
        'price'                 => 'السعر',
        'minimum_stock'         => 'الحد الأدنى للمخزون',
        'category_id'           => 'الفئة',
        'supplier_id'           => 'المورد',
        'distributor_id'        => 'الموزع',
        'warehouse_id'          => 'المخزن',
        'from_warehouse_id'     => 'مخزن المصدر',
        'to_warehouse_id'       => 'مخزن الجهة المستلمة',
        'product_id'            => 'المنتج',
        'reference_number'      => 'رقم المرجع',
        'receipt_date'          => 'تاريخ الاستلام',
        'issue_date'            => 'تاريخ الصرف',
        'transfer_date'         => 'تاريخ التحويل',
        'adjustment_date'       => 'تاريخ التسوية',
        'reason'                => 'السبب',
        'notes'                 => 'الملاحظات',
        'status'                => 'الحالة',
        'products'              => 'المنتجات',
        'quantities'            => 'الكميات',
        'unit_costs'            => 'تكاليف الوحدة',
        'directions'            => 'الاتجاهات',
        'date_from'             => 'من تاريخ',
        'date_to'               => 'إلى تاريخ',
        'search'                => 'البحث',
    ],

];
