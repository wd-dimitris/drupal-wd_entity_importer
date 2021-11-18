# CONTENTS OF THIS FILE

 * Introduction
 * Requirements
 * Recommended modules
 * Installation
 * Configuration
 * Maintainers


# INTRODUCTION

With WD Entity Importer, users can create or update entities from csv files. 

Currently the module provides only entity creation.


# REQUIREMENTS

No special requirements.


# RECOMMENDED MODULES

No recommended modules.

# INSTALLATION

Currently it's a custom module, not available in drupal repositories.

[Project on GitHub](https://github.com/wd-dimitris/drupal-wd_entity_importer)


# CONFIGURATION

No configuration is needed.

**Menu Link:** `Configuration/Development/WD Import Entities`  
**Path:** `/wd_entity_importer/form/importer`

## CSV Settings:
* Character encoding: UTF-8
* Separator: `,`
* String delimiter: `"`
* Multi value separator: `,`

## CSV Content

For the first row, each column must include the field's machine name 
and possibly other configuration info.

Each following row will consist of the corresponding field's value.

At the moment the module supports the following field types:  

|type | translation | multi value|
| --- | --- | --- |
| plain text / string | yes | no|
| text formatted (body) | yes | no|
| date (datetime coming soon) | no | no|
| media image | yes | no|
| taxonomy term | yes | yes|

## Language
If no field language_code, default language will be applied.

`Header:` language_code  
`Value:` en

|language_code |
|--- |
|en |

## CSV fields examples

### String field

`Header:` field_name  
`Value:` field value

|title | field_description| field_fruits |
|--- | --- | --- |
|node1 | node1 description| apple,orange,banana |

### String field Translation

`Header:` translation|language_code|filed_name  
`Value:` field value translation

|translation&#124;el&#124;title | translation&#124;el&#124;field_description|
|--- | ---|
|νοδε1 | νοδε1 περιγραφή|

### Path Alias field

`Header:` path  
`Value:` path with leading slash

|path |
|--- |
|/blog |

### Text (formatted) field, e.g. body

`Header:` text_formatted|format|field_name  
`Value:` field value

|text_formatted&#124;full_html&#124;body|
|---|
|body content, including html|

`Header:` text_formatted_summary|field_name  
`Value:` field value

|text_formatted_summary&#124;body|
|---|
|summary content|

### Text (formatted) field Translation

`Header:` translation|language_code|text_formatted|field_name  
`Value:` field value translation

|translation&#124;el&#124;text_formatted&#124;body|
|---|
|translated content, including html|

`Header:` translation|language_code|text_formatted_summary|field_name  
`Value:` field value translation

|translation&#124;el&#124;text_formatted_summary&#124;body|
|---|
|summary translated content|

### Date Time field

`Header:` datetime|filed_name  
`Value:` Y-m-d H:I

|datetime&#124;field_my_datetime|
|---|
|2019-12-04 13:45|

### Date field (Coming Soon)

`Header:` date|filed_name  
`Value:` Y-m-d

|date&#124;field_my_date|
|---|
|2019-12-04|

### Media Image Field

Upload all images to the specified path before import.

`Header:` media|media_bundle|filed_name  
`Value:` image file name

|media&#124;image&#124;field_my_image|
|---|
|image.jpg|

### Media Image Field Alt

Always include this field after media image field.

`Header:` alt|field_name  
`Value:` alt value

|alt&#124;field_my_image|
|---|
|image description|

### Media Image Field Alt Translation

Always include this field after media image field.

`Header:` translation|language_code|alt|field_name  
`Value:` alt value translation

|translation&#124;el&#124;alt&#124;field_my_image|
|---|
|περιγραφή εικόνας|

### Taxonomy Term

If the vocabulary is translated, for the value put the term name of the original translation.

`Header:` term|vocabulary_machine_name|field_name  
`Value:` term name

|term&#124;category&#124;field_category|
|---|
|category1|

### Taxonomy Term Auto Create

`Header:` term_auto|vocabulary_machine_name|field_name  
`Value:` term name

|term_auto&#124;tags&#124;field_tags|
|---|
|tag1|

### Entity Reference

`Header:` entity_reference|field_name  
`Value:` entity_id

|entity_reference&#124;field_country|
|---|
|12|


# MAINTAINERS

WebDimension
