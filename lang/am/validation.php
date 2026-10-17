<?php

/*
| Amharic messages for the validation rules the app uses. Rules not listed
| here fall back to English (APP_FALLBACK_LOCALE).
*/

return [

    'accepted' => ':attribute መቀበል አለብዎት።',
    'after' => ':attribute ከ:date በኋላ ያለ ቀን መሆን አለበት።',
    'array' => ':attribute ዝርዝር መሆን አለበት።',
    'before_or_equal' => ':attribute :date ወይም ከዚያ በፊት ያለ ቀን መሆን አለበት።',
    'boolean' => ':attribute አዎ ወይም አይ መሆን አለበት።',
    'confirmed' => 'የ:attribute ማረጋገጫው አይዛመድም።',
    'current_password' => 'የይለፍ ቃሉ ትክክል አይደለም።',
    'date' => ':attribute ትክክለኛ ቀን መሆን አለበት።',
    'distinct' => ':attribute ተደጋግሟል።',
    'email' => ':attribute ትክክለኛ የኢሜይል አድራሻ መሆን አለበት።',
    'enum' => 'የተመረጠው :attribute ትክክል አይደለም።',
    'exists' => 'የተመረጠው :attribute ትክክል አይደለም።',
    'extensions' => ':attribute ከእነዚህ አይነቶች አንዱ መሆን አለበት፦ :values።',
    'file' => ':attribute ፋይል መሆን አለበት።',
    'image' => ':attribute ምስል መሆን አለበት።',
    'in' => 'የተመረጠው :attribute ትክክል አይደለም።',
    'integer' => ':attribute ሙሉ ቁጥር መሆን አለበት።',
    'lowercase' => ':attribute በትንንሽ ፊደላት መሆን አለበት።',
    'max' => [
        'array' => ':attribute ከ:max በላይ ነገሮች ሊኖሩት አይችልም።',
        'file' => ':attribute ከ:max ኪሎባይት መብለጥ የለበትም።',
        'numeric' => ':attribute ከ:max መብለጥ የለበትም።',
        'string' => ':attribute ከ:max ፊደላት መብለጥ የለበትም።',
    ],
    'min' => [
        'array' => ':attribute ቢያንስ :min ነገሮች ሊኖሩት ይገባል።',
        'file' => ':attribute ቢያንስ :min ኪሎባይት መሆን አለበት።',
        'numeric' => ':attribute ቢያንስ :min መሆን አለበት።',
        'string' => ':attribute ቢያንስ :min ፊደላት መሆን አለበት።',
    ],
    'password' => [
        'letters' => ':attribute ቢያንስ አንድ ፊደል ሊኖረው ይገባል።',
        'mixed' => ':attribute ቢያንስ አንድ ትልቅና አንድ ትንሽ ፊደል ሊኖረው ይገባል።',
        'numbers' => ':attribute ቢያንስ አንድ ቁጥር ሊኖረው ይገባል።',
        'symbols' => ':attribute ቢያንስ አንድ ምልክት ሊኖረው ይገባል።',
        'uncompromised' => 'ይህ :attribute በተሰረቀ መረጃ ውስጥ ታይቷል። እባክዎ ሌላ :attribute ይምረጡ።',
    ],
    'required' => ':attribute ያስፈልጋል።',
    'required_if' => ':attribute ያስፈልጋል።',
    'string' => ':attribute ጽሑፍ መሆን አለበት።',
    'unique' => 'ይህ :attribute አስቀድሞ ተይዟል።',
    'uuid' => ':attribute ትክክል አይደለም።',

    'attributes' => [
        'access_ends_at' => 'መዳረሻ የሚያበቃበት ቀን',
        'asset_ids' => 'ፋይሎች',
        'brand_id' => 'ብራንድ',
        'brand_role' => 'በብራንዱ ላይ ያለ ሚና',
        'code' => 'ኮድ',
        'current_password' => 'የአሁኑ የይለፍ ቃል',
        'days' => 'ቀናት',
        'email' => 'ኢሜይል',
        'file' => 'ፋይል',
        'kind' => 'አይነት',
        'locale' => 'ቋንቋ',
        'name' => 'ስም',
        'partner_organisation' => 'ድርጅታቸው',
        'password' => 'የይለፍ ቃል',
        'recovery_code' => 'የመመለሻ ኮድ',
        'role' => 'ሚና',
        'tags' => 'መለያዎች',
        'title' => 'ርዕስ',
        'workspace' => 'የስራ ቦታ',
    ],

];
