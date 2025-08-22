<?php

return [
    'required' => ':attribute จำเป็นต้องกรอก',
    'email' => ':attribute ต้องเป็นรูปแบบอีเมลที่ถูกต้อง',
    'min' => [
        'string' => ':attribute ต้องมีอย่างน้อย :min ตัวอักษร',
    ],
    
    'attributes' => [
        'email' => 'อีเมล',
        'password' => 'รหัสผ่าน',
        'name' => 'ชื่อ',
        'full_name' => 'ชื่อ-นามสกุล',
        'phone' => 'เบอร์โทรศัพท์',
        'remember' => 'จดจำการเข้าสู่ระบบ',
    ],
];