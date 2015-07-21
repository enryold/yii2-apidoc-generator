# yii2-apidoc-generator
Yii2 Apidoc.js generator

Yii2 Apidoc.js generator is a little helper that generate Apidoc.js comments for your yii2 RESTFul API actions on-demand. 
Just call your API's endpoints to generate the comment files. Then run apidoc scripts to generate your documentation.


PREREQUISITES
-------------

Install Apidoc.js (http://apidocjs.com/)

```
npm install apidoc -g
```


INSTALL APIDOC GENERATOR WITH COMPOSER
---------------------------------------

```
 "eold/yii2-apidoc-generator": "^1.0."
 ```
 

CONFIGURE 
---------

Put apidoc-generator in your Yii2 config file components

```
'docGenerator' =>[
            'class' => 'eold\apidocgen\src\ApiDocGenerator',
            'isActive'=>true,                      // Flag to set plugin active
            'versionRegexFind'=>'/(\w+)(\d+)/i',   // regex used in preg_replace function to find Yii api version format (usually 'v1', 'vX') ... 
            'versionRegexReplace'=>'${2}.0.0',     // .. and replace it in Apidoc format (usually 'x.x.x')
            'docDataAlias'=>'@runtime/data_path'   // Folder to save output. make sure is writable. 
        ],
```
        
Then, add apidoc-generator in bootstrap section 

```
'bootstrap' => ['log', 'v1', 'docGenerator'],
```

USAGE
---------

Everytime you call an endpoint of your API's, ApiDocGenerator try to writes the corresponding Apidoc.js comment file.
If an output file was already generated it will NOT be overwrited. You have to delete it and call the endpoint again.

Then, you have to call Apidoc.js to generate the doc

```
apidoc -i <PATH_TO_YII2_APIDOC_GENERATOR_DATA_ALIAS> -o <PATH_TO_YOUR_GENERATED_DOCS>
```










