wti-multilang
=============

The most important functions for this plugin are:

    // Translates a given string into the current language of the requested page
    wti_multilang_get_translation('key.for.translation');

    // Returns the current language code of the requested page
    wti_multilang_get_current_language();

The most important shortcodes are:

    [t]key.for.translation[/t]

    [if lang="de"]Only visible in german.[/if]

Since we use Contact Form 7 a lot, there is also the possibility to translate keys in curly brackets:

    [select* happyness default:0 "{{very}}" "{{moderate}}" "{{suboptimal}}" "{{sad}}"]

