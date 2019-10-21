# CONTENTS OF THIS FILE

 * Introduction
 * Requirements
 * Recommended modules
 * Installation
 * Configuration
 * Maintainers


# INTRODUCTION

With WD Entity Importer, users can create or update entities from csv files. 

Currently the module provides only node creation.


# REQUIREMENTS

No special requirements.


# RECOMMENDED MODULES

For now, no recommended modules.

# INSTALLATION

Currently it's a custom module, not available in a repository.

[GitHub project](https://github.com/wd-dimitris/drupal-wd_entity_importer)


# CONFIGURATION

No configuration is needed.

## CSV Settings:
* Character encoding: UTF-8
* Separator: ,
* String delimiter: "

## CSV Content

For the first row, each column must include the field's machine name 
and possibly other configuration info.

Each following row will consist of the corresponding field's value.

At the moment the module supports string fields, media image fields and taxonomy term fields. It also supports the translation of these fields 

## CSV fields examples

### String field

`Header:` field_name  
`Value:` field value

|title | field_description|
|--- | ---|
|node1 | node1 description|

### String field Translation

`Header:` translation|language_code|filed_name  
`Value:` field value translation

|translation&#124;el&#124;title | translation&#124;el&#124;field_description|
|--- | ---|
|νοδε1 | νοδε1 περιγραφή|

### Media Image Field

Upload all images to `/sites/default/files/wd_entity_importer/images` before import.

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


# MAINTAINERS

WebDimension
