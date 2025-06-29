<?php
// app/Services/GoogleTranslateService.php

namespace App\Services;

use Google\Cloud\Translate\TranslateClient;
use App\Utils\Logger;

class GoogleTranslateService
{
    private $translate;
    private $rtlLanguages = [
        'ar', // Arabic
        'he', // Hebrew
        'fa', // Persian
        'ur', // Urdu
        'ps', // Pashto
        'sd', // Sindhi
        'ug', // Uyghur
        'yi', // Yiddish
    ];

    // Маппинг языков к странам для флагов
    private $languageToCountry = [
        'ab' => 'ge', // Abkhazian -> Georgia
        'ace' => 'id', // Acehnese -> Indonesia
        'ach' => 'ug', // Acoli -> Uganda
        'af' => 'za', // Afrikaans -> South Africa
        'ak' => 'gh', // Akan -> Ghana
        'alz' => 'cd', // Alur -> Congo
        'am' => 'et', // Amharic -> Ethiopia
        'ar' => 'sa', // Arabic -> Saudi Arabia
        'as' => 'in', // Assamese -> India
        'awa' => 'in', // Awadhi -> India
        'ay' => 'bo', // Aymara -> Bolivia
        'az' => 'az', // Azerbaijani -> Azerbaijan
        'ba' => 'ru', // Bashkir -> Russia
        'ban' => 'id', // Balinese -> Indonesia
        'bbc' => 'id', // Batak Toba -> Indonesia
        'be' => 'by', // Belarusian -> Belarus
        'bem' => 'zm', // Bemba -> Zambia
        'bn' => 'bd', // Bengali -> Bangladesh
        'bew' => 'id', // Betawi -> Indonesia
        'bho' => 'in', // Bhojpuri -> India
        'bik' => 'ph', // Bikol -> Philippines
        'bs' => 'ba', // Bosnian -> Bosnia
        'br' => 'fr', // Breton -> France
        'bg' => 'bg', // Bulgarian -> Bulgaria
        'bua' => 'ru', // Buriat -> Russia
        'yue' => 'cn', // Cantonese -> China
        'ca' => 'es', // Catalan -> Spain
        'ceb' => 'ph', // Cebuano -> Philippines
        'ny' => 'mw', // Chichewa -> Malawi
        'zh' => 'cn', // Chinese -> China
        'zh-TW' => 'tw', // Chinese (Traditional) -> Taiwan
        'cv' => 'ru', // Chuvash -> Russia
        'co' => 'fr', // Corsican -> France
        'crh' => 'ua', // Crimean Tatar -> Ukraine
        'hr' => 'hr', // Croatian -> Croatia
        'cs' => 'cz', // Czech -> Czech Republic
        'da' => 'dk', // Danish -> Denmark
        'dv' => 'mv', // Divehi -> Maldives
        'din' => 'ss', // Dinka -> South Sudan
        'doi' => 'in', // Dogri -> India
        'dov' => 'zw', // Dzongkha -> Zimbabwe
        'nl' => 'nl', // Dutch -> Netherlands
        'dz' => 'bt', // Dzongkha -> Bhutan
        'en' => 'gb', // English -> Great Britain
        'eo' => 'eo', // Esperanto -> Esperanto
        'et' => 'ee', // Estonian -> Estonia
        'ee' => 'gh', // Ewe -> Ghana
        'fj' => 'fj', // Fijian -> Fiji
        'tl' => 'ph', // Filipino -> Philippines
        'fi' => 'fi', // Finnish -> Finland
        'fr' => 'fr', // French -> France
        'fy' => 'nl', // Frisian -> Netherlands
        'ff' => 'sn', // Fulah -> Senegal
        'gaa' => 'gh', // Ga -> Ghana
        'gl' => 'es', // Galician -> Spain
        'ka' => 'ge', // Georgian -> Georgia
        'de' => 'de', // German -> Germany
        'el' => 'gr', // Greek -> Greece
        'gn' => 'py', // Guarani -> Paraguay
        'gu' => 'in', // Gujarati -> India
        'ht' => 'ht', // Haitian Creole -> Haiti
        'cnh' => 'mm', // Hakha Chin -> Myanmar
        'ha' => 'ng', // Hausa -> Nigeria
        'haw' => 'us', // Hawaiian -> USA
        'he' => 'il', // Hebrew -> Israel
        'hil' => 'ph', // Hiligaynon -> Philippines
        'hi' => 'in', // Hindi -> India
        'hmn' => 'cn', // Hmong -> China
        'hu' => 'hu', // Hungarian -> Hungary
        'hrx' => 'br', // Hunsrik -> Brazil
        'is' => 'is', // Icelandic -> Iceland
        'ig' => 'ng', // Igbo -> Nigeria
        'ilo' => 'ph', // Iloko -> Philippines
        'id' => 'id', // Indonesian -> Indonesia
        'ga' => 'ie', // Irish -> Ireland
        'it' => 'it', // Italian -> Italy
        'ja' => 'jp', // Japanese -> Japan
        'jw' => 'id', // Javanese -> Indonesia
        'kn' => 'in', // Kannada -> India
        'pam' => 'ph', // Kapampangan -> Philippines
        'kk' => 'kz', // Kazakh -> Kazakhstan
        'km' => 'kh', // Khmer -> Cambodia
        'cgg' => 'ug', // Chiga -> Uganda
        'rw' => 'rw', // Kinyarwanda -> Rwanda
        'ktu' => 'cg', // Kituba -> Congo
        'gom' => 'in', // Konkani -> India
        'ko' => 'kr', // Korean -> South Korea
        'kri' => 'sl', // Krio -> Sierra Leone
        'ku' => 'iq', // Kurdish -> Iraq
        'ckb' => 'iq', // Central Kurdish -> Iraq
        'ky' => 'kg', // Kyrgyz -> Kyrgyzstan
        'lo' => 'la', // Lao -> Laos
        'ltg' => 'lv', // Latgalian -> Latvia
        'la' => 'va', // Latin -> Vatican
        'lv' => 'lv', // Latvian -> Latvia
        'lij' => 'it', // Ligurian -> Italy
        'li' => 'nl', // Limburgish -> Netherlands
        'ln' => 'cd', // Lingala -> Congo
        'lt' => 'lt', // Lithuanian -> Lithuania
        'lmo' => 'it', // Lombard -> Italy
        'lg' => 'ug', // Ganda -> Uganda
        'luo' => 'ke', // Luo -> Kenya
        'lb' => 'lu', // Luxembourgish -> Luxembourg
        'mk' => 'mk', // Macedonian -> Macedonia
        'mai' => 'in', // Maithili -> India
        'mak' => 'id', // Makasar -> Indonesia
        'mg' => 'mg', // Malagasy -> Madagascar
        'ms' => 'my', // Malay -> Malaysia
        'ms-Arab' => 'my', // Malay (Arabic) -> Malaysia
        'ml' => 'in', // Malayalam -> India
        'mt' => 'mt', // Maltese -> Malta
        'mi' => 'nz', // Maori -> New Zealand
        'mr' => 'in', // Marathi -> India
        'chm' => 'ru', // Mari -> Russia
        'mni-Mtei' => 'in', // Manipuri -> India
        'min' => 'id', // Minangkabau -> Indonesia
        'lus' => 'in', // Mizo -> India
        'mn' => 'mn', // Mongolian -> Mongolia
        'my' => 'mm', // Burmese -> Myanmar
        'nr' => 'za', // Southern Ndebele -> South Africa
        'new' => 'np', // Newari -> Nepal
        'ne' => 'np', // Nepali -> Nepal
        'no' => 'no', // Norwegian -> Norway
        'nus' => 'ss', // Nuer -> South Sudan
        'oc' => 'fr', // Occitan -> France
        'or' => 'et', // Oromo -> Ethiopia
        'pag' => 'ph', // Pangasinan -> Philippines
        'pap' => 'aw', // Papiamento -> Aruba
        'ps' => 'af', // Pashto -> Afghanistan
        'fa' => 'ir', // Persian -> Iran
        'pl' => 'pl', // Polish -> Poland
        'pt' => 'pt', // Portuguese -> Portugal
        'pa' => 'in', // Punjabi -> India
        'pa-Arab' => 'pk', // Punjabi (Arabic) -> Pakistan
        'qu' => 'pe', // Quechua -> Peru
        'rom' => 'ro', // Romani -> Romania
        'ro' => 'ro', // Romanian -> Romania
        'rn' => 'bi', // Rundi -> Burundi
        'ru' => 'ru', // Russian -> Russia
        'sm' => 'ws', // Samoan -> Samoa
        'sg' => 'cf', // Sango -> Central African Republic
        'sa' => 'in', // Sanskrit -> India
        'gd' => 'gb', // Scottish Gaelic -> Great Britain
        'nso' => 'za', // Northern Sotho -> South Africa
        'sr' => 'rs', // Serbian -> Serbia
        'st' => 'ls', // Sesotho -> Lesotho
        'crs' => 'sc', // Seychellois Creole -> Seychelles
        'shn' => 'mm', // Shan -> Myanmar
        'sn' => 'zw', // Shona -> Zimbabwe
        'scn' => 'it', // Sicilian -> Italy
        'szl' => 'pl', // Silesian -> Poland
        'sd' => 'pk', // Sindhi -> Pakistan
        'si' => 'lk', // Sinhala -> Sri Lanka
        'sk' => 'sk', // Slovak -> Slovakia
        'sl' => 'si', // Slovenian -> Slovenia
        'so' => 'so', // Somali -> Somalia
        'es' => 'es', // Spanish -> Spain
        'su' => 'id', // Sundanese -> Indonesia
        'sw' => 'tz', // Swahili -> Tanzania
        'ss' => 'sz', // Swati -> Swaziland
        'sv' => 'se', // Swedish -> Sweden
        'tg' => 'tj', // Tajik -> Tajikistan
        'ta' => 'in', // Tamil -> India
        'tt' => 'ru', // Tatar -> Russia
        'te' => 'in', // Telugu -> India
        'tet' => 'tl', // Tetum -> Timor-Leste
        'th' => 'th', // Thai -> Thailand
        'ti' => 'er', // Tigrinya -> Eritrea
        'ts' => 'za', // Tsonga -> South Africa
        'tn' => 'bw', // Tswana -> Botswana
        'tr' => 'tr', // Turkish -> Turkey
        'tk' => 'tm', // Turkmen -> Turkmenistan
        'uk' => 'ua', // Ukrainian -> Ukraine
        'ur' => 'pk', // Urdu -> Pakistan
        'ug' => 'cn', // Uyghur -> China
        'uz' => 'uz', // Uzbek -> Uzbekistan
        'vi' => 'vn', // Vietnamese -> Vietnam
        'cy' => 'gb', // Welsh -> Great Britain
        'xh' => 'za', // Xhosa -> South Africa
        'yi' => 'il', // Yiddish -> Israel
        'yo' => 'ng', // Yoruba -> Nigeria
        'yua' => 'mx', // Yucatec Maya -> Mexico
        'zu' => 'za', // Zulu -> South Africa
        'jv' => 'id', // Javanese -> Indonesia
        'zh-CN' => 'cn', // Chinese (Simplified) -> China
        'sq' => 'al', // Albanian -> Albania
        'hy' => 'am', // Armenian -> Armenia
        'eu' => 'es', // Basque -> Spain
        'btx' => 'id', // Batak Karo -> Indonesia
        'bts' => 'id', // Batak Simalungun -> Indonesia
        'iw' => 'il', // Hebrew (old code) -> Israel
        'bm' => 'ml', // Bambara -> Mali
    ];

    // Названия языков на английском
    private $languageNames = [
        'ab' => 'Abkhazian',
        'ace' => 'Acehnese',
        'ach' => 'Acoli',
        'af' => 'Afrikaans',
        'ak' => 'Akan',
        'alz' => 'Alur',
        'am' => 'Amharic',
        'ar' => 'Arabic',
        'as' => 'Assamese',
        'awa' => 'Awadhi',
        'ay' => 'Aymara',
        'az' => 'Azerbaijani',
        'ba' => 'Bashkir',
        'ban' => 'Balinese',
        'bbc' => 'Batak Toba',
        'be' => 'Belarusian',
        'bem' => 'Bemba',
        'bn' => 'Bengali',
        'bew' => 'Betawi',
        'bho' => 'Bhojpuri',
        'bik' => 'Bikol',
        'bs' => 'Bosnian',
        'br' => 'Breton',
        'bg' => 'Bulgarian',
        'bua' => 'Buriat',
        'yue' => 'Cantonese',
        'ca' => 'Catalan',
        'ceb' => 'Cebuano',
        'ny' => 'Chichewa',
        'zh' => 'Chinese',
        'zh-TW' => 'Chinese (Traditional)',
        'cv' => 'Chuvash',
        'co' => 'Corsican',
        'crh' => 'Crimean Tatar',
        'hr' => 'Croatian',
        'cs' => 'Czech',
        'da' => 'Danish',
        'dv' => 'Divehi',
        'din' => 'Dinka',
        'doi' => 'Dogri',
        'dov' => 'Dzongkha',
        'nl' => 'Dutch',
        'dz' => 'Dzongkha',
        'en' => 'English',
        'eo' => 'Esperanto',
        'et' => 'Estonian',
        'ee' => 'Ewe',
        'fj' => 'Fijian',
        'tl' => 'Filipino',
        'fi' => 'Finnish',
        'fr' => 'French',
        'fy' => 'Frisian',
        'ff' => 'Fulah',
        'gaa' => 'Ga',
        'gl' => 'Galician',
        'ka' => 'Georgian',
        'de' => 'German',
        'el' => 'Greek',
        'gn' => 'Guarani',
        'gu' => 'Gujarati',
        'ht' => 'Haitian Creole',
        'cnh' => 'Hakha Chin',
        'ha' => 'Hausa',
        'haw' => 'Hawaiian',
        'he' => 'Hebrew',
        'hil' => 'Hiligaynon',
        'hi' => 'Hindi',
        'hmn' => 'Hmong',
        'hu' => 'Hungarian',
        'hrx' => 'Hunsrik',
        'is' => 'Icelandic',
        'ig' => 'Igbo',
        'ilo' => 'Iloko',
        'id' => 'Indonesian',
        'ga' => 'Irish',
        'it' => 'Italian',
        'ja' => 'Japanese',
        'jw' => 'Javanese',
        'kn' => 'Kannada',
        'pam' => 'Kapampangan',
        'kk' => 'Kazakh',
        'km' => 'Khmer',
        'cgg' => 'Chiga',
        'rw' => 'Kinyarwanda',
        'ktu' => 'Kituba',
        'gom' => 'Konkani',
        'ko' => 'Korean',
        'kri' => 'Krio',
        'ku' => 'Kurdish',
        'ckb' => 'Central Kurdish',
        'ky' => 'Kyrgyz',
        'lo' => 'Lao',
        'ltg' => 'Latgalian',
        'la' => 'Latin',
        'lv' => 'Latvian',
        'lij' => 'Ligurian',
        'li' => 'Limburgish',
        'ln' => 'Lingala',
        'lt' => 'Lithuanian',
        'lmo' => 'Lombard',
        'lg' => 'Ganda',
        'luo' => 'Luo',
        'lb' => 'Luxembourgish',
        'mk' => 'Macedonian',
        'mai' => 'Maithili',
        'mak' => 'Makasar',
        'mg' => 'Malagasy',
        'ms' => 'Malay',
        'ms-Arab' => 'Malay (Arabic)',
        'ml' => 'Malayalam',
        'mt' => 'Maltese',
        'mi' => 'Maori',
        'mr' => 'Marathi',
        'chm' => 'Mari',
        'mni-Mtei' => 'Manipuri',
        'min' => 'Minangkabau',
        'lus' => 'Mizo',
        'mn' => 'Mongolian',
        'my' => 'Burmese',
        'nr' => 'Southern Ndebele',
        'new' => 'Newari',
        'ne' => 'Nepali',
        'no' => 'Norwegian',
        'nus' => 'Nuer',
        'oc' => 'Occitan',
        'or' => 'Odia',
        'om' => 'Oromo',
        'pag' => 'Pangasinan',
        'pap' => 'Papiamento',
        'ps' => 'Pashto',
        'fa' => 'Persian',
        'pl' => 'Polish',
        'pt' => 'Portuguese',
        'pa' => 'Punjabi',
        'pa-Arab' => 'Punjabi (Arabic)',
        'qu' => 'Quechua',
        'rom' => 'Romani',
        'ro' => 'Romanian',
        'rn' => 'Rundi',
        'ru' => 'Russian',
        'sm' => 'Samoan',
        'sg' => 'Sango',
        'sa' => 'Sanskrit',
        'gd' => 'Scottish Gaelic',
        'nso' => 'Northern Sotho',
        'sr' => 'Serbian',
        'st' => 'Sesotho',
        'crs' => 'Seychellois Creole',
        'shn' => 'Shan',
        'sn' => 'Shona',
        'scn' => 'Sicilian',
        'szl' => 'Silesian',
        'sd' => 'Sindhi',
        'si' => 'Sinhala',
        'sk' => 'Slovak',
        'sl' => 'Slovenian',
        'so' => 'Somali',
        'es' => 'Spanish',
        'su' => 'Sundanese',
        'sw' => 'Swahili',
        'ss' => 'Swati',
        'sv' => 'Swedish',
        'tg' => 'Tajik',
        'ta' => 'Tamil',
        'tt' => 'Tatar',
        'te' => 'Telugu',
        'tet' => 'Tetum',
        'th' => 'Thai',
        'ti' => 'Tigrinya',
        'ts' => 'Tsonga',
        'tn' => 'Tswana',
        'tr' => 'Turkish',
        'tk' => 'Turkmen',
        'uk' => 'Ukrainian',
        'ur' => 'Urdu',
        'ug' => 'Uyghur',
        'uz' => 'Uzbek',
        'vi' => 'Vietnamese',
        'cy' => 'Welsh',
        'xh' => 'Xhosa',
        'yi' => 'Yiddish',
        'yo' => 'Yoruba',
        'yua' => 'Yucatec Maya',
        'zu' => 'Zulu',
        'jv' => 'Javanese',
        'zh-CN' => 'Chinese (Simplified)'
    ];

    // Названия языков на родном языке
    private $nativeLanguageNames = [
        'ab' => 'Аҧсуа',
        'ace' => 'Bahsa Acèh',
        'ach' => 'Lëblaŋo',
        'af' => 'Afrikaans',
        'ak' => 'Akan',
        'alz' => 'Dhok',
        'am' => 'አማርኛ',
        'ar' => 'العربية',
        'as' => 'অসমীয়া',
        'awa' => 'अवधी',
        'ay' => 'Aymar aru',
        'az' => 'Azərbaycan',
        'ba' => 'Башҡорт',
        'ban' => 'Basa Bali',
        'bbc' => 'Hata Batak',
        'be' => 'Беларуская',
        'bem' => 'Ichibemba',
        'bn' => 'বাংলা',
        'bew' => 'Bahasa Betawi',
        'bho' => 'भोजपुरी',
        'bik' => 'Bikol',
        'bs' => 'Bosanski',
        'br' => 'Brezhoneg',
        'bg' => 'Български',
        'bua' => 'Буряад',
        'yue' => '粵語',
        'ca' => 'Català',
        'ceb' => 'Cebuano',
        'ny' => 'Chichewa',
        'zh' => '中文',
        'zh-TW' => '繁體中文',
        'cv' => 'Чӑваш',
        'co' => 'Corsu',
        'crh' => 'Къырымтатарджа',
        'hr' => 'Hrvatski',
        'cs' => 'Čeština',
        'da' => 'Dansk',
        'dv' => 'ދިވެހި',
        'din' => 'Thuɔŋjäŋ',
        'doi' => 'डोगरी',
        'dov' => 'chiShona',
        'nl' => 'Nederlands',
        'dz' => 'རྫོང་ཁ',
        'en' => 'English',
        'eo' => 'Esperanto',
        'et' => 'Eesti',
        'ee' => 'Eʋegbe',
        'fj' => 'Vosa Vakaviti',
        'tl' => 'Filipino',
        'fi' => 'Suomi',
        'fr' => 'Français',
        'fy' => 'Frysk',
        'ff' => 'Fulfulde',
        'gaa' => 'Ga',
        'gl' => 'Galego',
        'ka' => 'ქართული',
        'de' => 'Deutsch',
        'el' => 'Ελληνικά',
        'gn' => 'Avañe\'ẽ',
        'gu' => 'ગુજરાતી',
        'ht' => 'Kreyòl Ayisyen',
        'cnh' => 'Hakha Chin',
        'ha' => 'Hausa',
        'haw' => 'ʻŌlelo Hawaiʻi',
        'he' => 'עברית',
        'hil' => 'Ilonggo',
        'hi' => 'हिन्दी',
        'hmn' => 'Hmong',
        'hu' => 'Magyar',
        'hrx' => 'Hunsrik',
        'is' => 'Íslenska',
        'ig' => 'Igbo',
        'ilo' => 'Ilokano',
        'id' => 'Bahasa Indonesia',
        'ga' => 'Gaeilge',
        'it' => 'Italiano',
        'ja' => '日本語',
        'jw' => 'Basa Jawa',
        'kn' => 'ಕನ್ನಡ',
        'pam' => 'Kapampangan',
        'kk' => 'Қазақ',
        'km' => 'ខ្មែរ',
        'cgg' => 'Rukiga',
        'rw' => 'Ikinyarwanda',
        'ktu' => 'Kituba',
        'gom' => 'कोंकणी',
        'ko' => '한국어',
        'kri' => 'Krio',
        'ku' => 'Kurdî',
        'ckb' => 'کوردی',
        'ky' => 'Кыргызча',
        'lo' => 'ລາວ',
        'ltg' => 'Latgalīšu',
        'la' => 'Latina',
        'lv' => 'Latviešu',
        'lij' => 'Ligure',
        'li' => 'Limburgs',
        'ln' => 'Lingála',
        'lt' => 'Lietuvių',
        'lmo' => 'Lombard',
        'lg' => 'Luganda',
        'luo' => 'Dholuo',
        'lb' => 'Lëtzebuergesch',
        'mk' => 'Македонски',
        'mai' => 'मैथिली',
        'mak' => 'Basa Mangkasara',
        'mg' => 'Malagasy',
        'ms' => 'Bahasa Melayu',
        'ms-Arab' => 'بهاس ملايو',
        'ml' => 'മലയാളം',
        'mt' => 'Malti',
        'mi' => 'Māori',
        'mr' => 'मराठी',
        'chm' => 'Марий',
        'mni-Mtei' => 'মৈতৈলোন্',
        'min' => 'Baso Minangkabau',
        'lus' => 'Mizo ṭawng',
        'mn' => 'Монгол',
        'my' => 'မြန်မာ',
        'nr' => 'isiNdebele',
        'new' => 'नेपाल भाषा',
        'ne' => 'नेपाली',
        'no' => 'Norsk',
        'nus' => 'Thok Nath',
        'oc' => 'Occitan',
        'or' => 'ଓଡ଼ିଆ',
        'om' => 'Afaan Oromoo',
        'pag' => 'Pangasinan',
        'pap' => 'Papiamento',
        'ps' => 'پښتو',
        'fa' => 'فارسی',
        'pl' => 'Polski',
        'pt' => 'Português',
        'pa' => 'ਪੰਜਾਬੀ',
        'pa-Arab' => 'پنجابی',
        'qu' => 'Runasimi',
        'rom' => 'Romani',
        'ro' => 'Română',
        'rn' => 'Ikirundi',
        'ru' => 'Русский',
        'sm' => 'Gagana Sāmoa',
        'sg' => 'Sängö',
        'sa' => 'संस्कृतम्',
        'gd' => 'Gàidhlig',
        'nso' => 'Sesotho sa Leboa',
        'sr' => 'Српски',
        'st' => 'Sesotho',
        'crs' => 'Seselwa',
        'shn' => 'ၽႃႇသႃႇတႆး',
        'sn' => 'chiShona',
        'scn' => 'Sicilianu',
        'szl' => 'Ślōnski',
        'sd' => 'सिन्धी',
        'si' => 'සිංහල',
        'sk' => 'Slovenčina',
        'sl' => 'Slovenščina',
        'so' => 'Soomaali',
        'es' => 'Español',
        'su' => 'Basa Sunda',
        'sw' => 'Kiswahili',
        'ss' => 'siSwati',
        'sv' => 'Svenska',
        'tg' => 'Тоҷикӣ',
        'ta' => 'தமிழ்',
        'tt' => 'Татар',
        'te' => 'తెలుగు',
        'tet' => 'Tetun',
        'th' => 'ไทย',
        'ti' => 'ትግርኛ',
        'ts' => 'Xitsonga',
        'tn' => 'Setswana',
        'tr' => 'Türkçe',
        'tk' => 'Türkmen',
        'uk' => 'Українська',
        'ur' => 'اردو',
        'ug' => 'ئۇيغۇرچە',
        'uz' => 'Oʻzbek',
        'vi' => 'Tiếng Việt',
        'cy' => 'Cymraeg',
        'xh' => 'isiXhosa',
        'yi' => 'ייִדיש',
        'yo' => 'Yorùbá',
        'yua' => 'Màaya t\'àan',
        'zu' => 'isiZulu',
        'jv' => 'Basa Jawa',
        'zh-CN' => '简体中文'
    ];

    public function __construct()
    {
        $this->translate = new TranslateClient([
            'key' => $_ENV['GOOGLE_TRANSLATE_API_KEY'],
            'timeout' => 5, // 5 секунд таймаут
            'retries' => 2  // 2 попытки
        ]);
    }

    /**
     * Get list of RTL languages
     * 
     * @return array List of RTL language codes
     */
    public function getRtlLanguages(): array
    {
        return $this->rtlLanguages;
    }

    /**
     * Get language names from Google Translate API
     * 
     * @param string $locale Language code
     * @return array|null Array with language names or null if not found
     */
    public function getLanguageNames(string $locale): ?array
    {
        try {
            // Получаем название на английском
            $enResult = $this->translate->translate('Language', [
                'target' => 'en',
                'source' => $locale
            ]);

            // Получаем название на родном языке
            $nativeResult = $this->translate->translate('Language', [
                'target' => $locale,
                'source' => 'en'
            ]);

            return [
                'name' => $enResult['text'] ?? null,
                'native_name' => $nativeResult['text'] ?? null
            ];

        } catch (\Exception $e) {
            Logger::error(
                $e->getMessage(),
                'GoogleTranslateService::getLanguageNames',
                ['locale' => $locale]
            );
            return null;
        }
    }

    /**
     * Get native name of the language
     * 
     * @param string $locale Language code
     * @return string Native name
     */
    private function getNativeName(string $locale): string
    {
        try {
            $languages = $this->translate->languages(['target' => $locale]);
            foreach ($languages as $language) {
                if ($language['language'] === $locale) {
                    return $language['name'];
                }
            }
            return $locale;
        } catch (\Exception $e) {
            Logger::error(
                $e->getMessage(),
                'GoogleTranslateService::getNativeName',
                ['locale' => $locale]
            );
            return $locale;
        }
    }

    /**
     * Translate text with direction
     * 
     * @param string $text Text to translate
     * @param string $targetLocale Target language code
     * @return array|null Array with translated text and direction or null on error
     */
    public function translate(string $text, string $targetLocale): ?array
    {
        try {
            $result = $this->translate->translate($text, [
                'target' => $targetLocale,
                'source' => 'en'
            ]);

            return [
                'text' => $result['text'] ?? null,
                'direction' => in_array($targetLocale, $this->rtlLanguages) ? 'rtl' : 'ltr'
            ];

        } catch (\Exception $e) {
            Logger::error(
                $e->getMessage(),
                'GoogleTranslateService::translate',
                [
                    'text' => $text,
                    'targetLocale' => $targetLocale,
                    'error' => $e->getMessage()
                ]
            );
            return null;
        }
    }

    /**
     * Get list of supported languages
     * 
     * @return array List of supported languages
     */
    public function getSupportedLanguages(): array
    {
        try {
            Logger::info(
                'Getting supported languages from Google Translate',
                'GoogleTranslateService::getSupportedLanguages'
            );
            
            // Получаем список языков
            $languages = $this->translate->languages(['target' => 'en']);
            
            Logger::info(
                'Raw response from Google Translate',
                'GoogleTranslateService::getSupportedLanguages',
                ['raw_response' => $languages]
            );
            
            // Проверяем структуру ответа
            if (!is_array($languages)) {
                Logger::error(
                    'Invalid response type from Google Translate',
                    'GoogleTranslateService::getSupportedLanguages',
                    ['type' => gettype($languages)]
                );
                return [];
            }
            
            // Преобразуем в нужный формат
            $formattedLanguages = [];
            foreach ($languages as $code) {
                // Для английского языка используем фиксированные значения
                $flag = strtolower($this->languageToCountry[$code] ?? 'globe');
                if ($code === 'en') {
                    $formattedLanguages[] = [
                        'language' => $code,
                        'name' => 'English',
                        'native_name' => 'English',
                        'country_code' => 'gb',
                        'flag' => '🇬🇧'                    
                    ];
                    continue;
                }
                
                try {
                    // Получаем название языка на английском из нашего списка
                    $name = $this->languageNames[$code] ?? $code;
                    
                    // Получаем название языка на родном языке из нашего списка
                    $nativeName = $this->nativeLanguageNames[$code] ?? $code;
                    
                    // Получаем код страны для флага
                    $countryCode = strtoupper($this->languageToCountry[$code] ?? 'UN'); // UN — United Nations/Globe
                    
                    $flag = strtolower($this->languageToCountry[$code] ?? 'globe');
                    $formattedLanguages[] = [
                        'language' => $code,
                        'name' => $name,
                        'native_name' => $nativeName,
                        'country_code' => $countryCode,
                        'flag' => $flag
                    ];
                } catch (\Exception $e) {
                    Logger::warning(
                        'Failed to get language name',
                        'GoogleTranslateService::getSupportedLanguages',
                        [
                            'code' => $code,
                            'error' => $e->getMessage()
                        ]
                    );
                    $flag = strtolower($this->languageToCountry[$code] ?? 'globe');
                    // Если не удалось получить название, используем код
                    $formattedLanguages[] = [
                        'language' => $code,
                        'name' => $code,
                        'native_name' => $code,
                        'country_code' => $code,
                        'flag' => $flag
                    ];
                }
            }
            
            Logger::info(
                'Languages formatted successfully',
                'GoogleTranslateService::getSupportedLanguages',
                ['formatted_languages' => $formattedLanguages]
            );
            
            return $formattedLanguages;
        } catch (\Exception $e) {
            Logger::error(
                $e->getMessage(),
                'GoogleTranslateService::getSupportedLanguages',
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]
            );
            return [];
        }
    }

    /**
     * Translate HTML content with variable substitution protection
     * 
     * @param string $htmlContent HTML content to translate
     * @param string $targetLocale Target language code
     * @return array|null Array with translated text and direction or null on error
     */
    public function translateHtml(string $htmlContent, string $targetLocale): ?array
    {
        try {
            Logger::info(
                'Starting HTML translation',
                'GoogleTranslateService::translateHtml',
                [
                    'targetLocale' => $targetLocale,
                    'contentLength' => strlen($htmlContent)
                ]
            );

            // Шаг 1: Находим и сохраняем все переменные подстановки {{variable}}
            $variables = [];
            $variableCounter = 0;
            
            // Регулярное выражение для поиска переменных в формате {{variable}}
            $pattern = '/\{\{([^}]+)\}\}/';
            
            $htmlWithPlaceholders = preg_replace_callback($pattern, function($matches) use (&$variables, &$variableCounter) {
                $variableName = $matches[1];
                $placeholder = "___VAR_{$variableCounter}___";
                $variables[$placeholder] = $matches[0]; // Сохраняем оригинальную переменную
                $variableCounter++;
                return $placeholder;
            }, $htmlContent);

            Logger::info(
                'Variables extracted from HTML',
                'GoogleTranslateService::translateHtml',
                [
                    'variables_count' => count($variables),
                    'variables' => $variables
                ]
            );

            // Шаг 2: Переводим HTML с замененными переменными
            $translationResult = $this->translate($htmlWithPlaceholders, $targetLocale);
            
            if (!$translationResult) {
                Logger::error(
                    'Translation failed',
                    'GoogleTranslateService::translateHtml',
                    [
                        'targetLocale' => $targetLocale,
                        'htmlWithPlaceholders' => $htmlWithPlaceholders
                    ]
                );
                return null;
            }

            $translatedHtml = $translationResult['text'];

            // Шаг 3: Восстанавливаем переменные подстановки
            foreach ($variables as $placeholder => $originalVariable) {
                $translatedHtml = str_replace($placeholder, $originalVariable, $translatedHtml);
            }

            Logger::info(
                'HTML translation completed successfully',
                'GoogleTranslateService::translateHtml',
                [
                    'targetLocale' => $targetLocale,
                    'variables_restored' => count($variables),
                    'finalLength' => strlen($translatedHtml)
                ]
            );

            return [
                'text' => $translatedHtml,
                'direction' => $translationResult['direction']
            ];

        } catch (\Exception $e) {
            Logger::error(
                'Error in HTML translation',
                'GoogleTranslateService::translateHtml',
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'targetLocale' => $targetLocale,
                    'htmlContent' => $htmlContent
                ]
            );
            return null;
        }
    }

    /**
     * Translate plain text with variable substitution protection
     * 
     * @param string $text Plain text to translate
     * @param string $targetLocale Target language code
     * @return array|null Array with translated text and direction or null on error
     */
    public function translateTextWithVariables(string $text, string $targetLocale): ?array
    {
        try {
            Logger::info(
                'Starting text translation with variable protection',
                'GoogleTranslateService::translateTextWithVariables',
                [
                    'targetLocale' => $targetLocale,
                    'textLength' => strlen($text)
                ]
            );

            // Шаг 1: Находим и сохраняем все переменные подстановки {{variable}}
            $variables = [];
            $variableCounter = 0;
            
            // Регулярное выражение для поиска переменных в формате {{variable}}
            $pattern = '/\{\{([^}]+)\}\}/';
            
            $textWithPlaceholders = preg_replace_callback($pattern, function($matches) use (&$variables, &$variableCounter) {
                $variableName = $matches[1];
                $placeholder = "___VAR_{$variableCounter}___";
                $variables[$placeholder] = $matches[0]; // Сохраняем оригинальную переменную
                $variableCounter++;
                return $placeholder;
            }, $text);

            Logger::info(
                'Variables extracted from text',
                'GoogleTranslateService::translateTextWithVariables',
                [
                    'variables_count' => count($variables),
                    'variables' => $variables
                ]
            );

            // Шаг 2: Переводим текст с замененными переменными
            $translationResult = $this->translate($textWithPlaceholders, $targetLocale);
            
            if (!$translationResult) {
                Logger::error(
                    'Translation failed',
                    'GoogleTranslateService::translateTextWithVariables',
                    [
                        'targetLocale' => $targetLocale,
                        'textWithPlaceholders' => $textWithPlaceholders
                    ]
                );
                return null;
            }

            $translatedText = $translationResult['text'];

            // Шаг 3: Восстанавливаем переменные подстановки
            foreach ($variables as $placeholder => $originalVariable) {
                $translatedText = str_replace($placeholder, $originalVariable, $translatedText);
            }

            Logger::info(
                'Text translation with variables completed successfully',
                'GoogleTranslateService::translateTextWithVariables',
                [
                    'targetLocale' => $targetLocale,
                    'variables_restored' => count($variables),
                    'finalLength' => strlen($translatedText)
                ]
            );

            return [
                'text' => $translatedText,
                'direction' => $translationResult['direction']
            ];

        } catch (\Exception $e) {
            Logger::error(
                'Error in text translation with variables',
                'GoogleTranslateService::translateTextWithVariables',
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'targetLocale' => $targetLocale,
                    'text' => $text
                ]
            );
            return null;
        }
    }
}