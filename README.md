# BingTranslationBundle
Bing translation service for Symfony2

#Installation
Just add this line to composer:
```
"melk/bing-translation" : "dev-master"
```

Then add bundle to your `AppKernel.php` file:
```
new Melk\BingTranslationBundle\MelkBingTranslationBundle(),
```

#Configuration
Create MS application and add next information to your `config.yml`:
```
#app/config/config.yml
melk_bing_translation:
    client_id: your client id
    client_secret: your client secret
```

#Usage
Simple example of how to translate comments in controller:
```
    $bingTranslator = $this->get('melk_bing_translation.translator');
    $commentLocale = $bingTranslator->detectLanguage($comment);
    $translated = $bingTranslator->translate($comment, $commentLocale, $toLocale);
```

