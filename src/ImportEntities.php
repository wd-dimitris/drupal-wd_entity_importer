<?php

namespace Drupal\wd_entity_importer;

use Drupal;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\File\FileSystemInterface;
use Drupal\media\Entity\Media;

class ImportEntities{

    public static function import($csv_uri, $entityType, $entityBundle, $sourcePath, $destinationPath, &$context){

        if(empty($destinationPath)){
            $dt = new DrupalDateTime();
            $destinationPath = $dt->format('Y-m').'-imported';
        }

        $user = Drupal::currentUser();
        $uid = $user->id();

        $defaultLanguage = Drupal::languageManager()->getCurrentLanguage()->getId();

        if (!isset($context['sandbox']['offset'])) {
            $context['sandbox']['offset'] = 0;
            $context['sandbox']['records'] = 0;
        }

        $file_handle = fopen($csv_uri, 'r');

        if ( $file_handle === FALSE ) {
            // Failed to open file
            Drupal::logger('csvImporter')->error('Failed to open '.$csv_uri);
            $context['finished'] = TRUE;
            return;
        }

        $ret = fseek($file_handle, $context['sandbox']['offset']);

        if ( $ret != 0 ) {
            // Failed to seek
            Drupal::logger('csvImporter')->error('Failed to seek to '.$context['sandbox']['offset']);
            $context['finished'] = TRUE;
            return;
        }

        $limit = 500;  // Maximum number of rows to process at a time
        $done = FALSE;

        $firstRow = TRUE;

        $languages = [];

        $fields = [];
        $fieldsMap = [];
        for ( $i = 0; $i < $limit; $i++ ) {
            $data = fgetcsv($file_handle, 10000, ",");

            if ( $data === FALSE ) {
                $done = TRUE;
                // No more records to process
                break;
            }
            else {
                $context['sandbox']['records']++;
                $context['sandbox']['offset'] = ftell($file_handle);
                $context['sandbox']['records'];

                // Get first row field info
                if($firstRow){
                    foreach($data as $key => $field) {
                        // $fieldType = $entity->get($field)->getFieldDefinition()->getType();
                        // WD-TODO: check if field is translatable
                        //$isTranslatable = $entity->get($field)->getFieldDefinition()->isTranslatable();

                        // Regular field
                        if (!strpos($field, '|')) { // ['field_name']
                            $fields[$field]['name'] = $field;
                            $fields[$field]['type'] = 'string';
                            $fields[$field]['value'] = '';
                            $fieldsMap[$key]['field'] = $field;
                            $fieldsMap[$key]['type'] = 'string';
                        } // Special field
                        else {
                            $fieldList = explode('|', $field);
                            $fieldListCount = count($fieldList);
                            switch ($fieldList[0]) {
                                case 'text_formatted': // ['text_formatted', 'full_html', 'body']
                                    $fields[$fieldList[2]]['name'] = $fieldList[2];
                                    $fields[$fieldList[2]]['type'] = 'text_formatted';
                                    $fieldsMap[$key]['field'] = $fieldList[2];
                                    $fieldsMap[$key]['type'] = 'text_formatted';
                                    $fieldsMap[$key]['format'] = $fieldList[1];
                                    break;
                                case 'text_formatted_summary': // ['text_formatted', 'body']
                                    $fieldsMap[$key]['field'] = $fieldList[1];
                                    $fieldsMap[$key]['type'] = 'text_formatted_summary';
                                    break;
                                case 'entity_reference': // ['entity_reference', 'field_country']
                                    $fields[ $fieldList[1]]['name'] =  $fieldList[1];
                                    $fields[ $fieldList[1]]['type'] = 'entity_reference';
                                    $fields[ $fieldList[1]]['value'] = '';
                                    $fieldsMap[$key]['field'] = $fieldList[1];
                                    $fieldsMap[$key]['type'] = 'entity_reference';
                                    break;
                                // WD-TODO: support all media types
                                // WD-TODO: support custom media image field
                                // WD-TODO: support multi value media field
                                // WD-TODO: Check if field/media is translatable
                                case 'media': // ['media', 'image', 'field_media_image']
                                    if($fieldList[1] == 'image') {
                                        $fields[$fieldList[2]]['name'] = $fieldList[2];
                                        $fields[$fieldList[2]]['type'] = 'media_image';
                                        $fields[$fieldList[2]]['value'] = '';
                                        $fieldsMap[$key]['field'] = $fieldList[2];
                                        $fieldsMap[$key]['type'] = 'media_image_uri';
                                    }
                                    break;
                                // Media image alt
                                case 'alt': // ['alt', 'field_media_image']
                                    $fields[$fieldList[1]]['alt']['value'] = '';
                                    $fieldsMap[$key]['field'] = $fieldList[1];
                                    $fieldsMap[$key]['type'] = 'media_image_alt';
                                    break;
                                // Taxonomy term
                                case 'term': // ['term', 'vocabulary id', 'field_name']
                                    $fields[$fieldList[2]]['vid'] = $fieldList[1];
                                    $fields[$fieldList[2]]['type'] = 'taxonomy_term';
                                    $fields[$fieldList[2]]['value'] = '';
                                    $fieldsMap[$key]['field'] = $fieldList[2];
                                    $fieldsMap[$key]['type'] = 'taxonomy_term';
                                    break;
                                case 'term_auto': // ['term_auto', 'vocabulary id', 'field_name']
                                    $fields[$fieldList[2]]['vid'] = $fieldList[1];
                                    $fields[$fieldList[2]]['type'] = 'taxonomy_term_auto_create';
                                    $fields[$fieldList[2]]['value'] = '';
                                    $fieldsMap[$key]['field'] = $fieldList[2];
                                    $fieldsMap[$key]['type'] = 'taxonomy_term_auto_create';
                                    break;
                                case 'datetime':
                                    $fields[$fieldList[1]]['name'] = $fieldList[1];
                                    $fields[$fieldList[1]]['type'] = 'datetime';
                                    $fieldsMap[$key]['field'] = $fieldList[1];
                                    $fieldsMap[$key]['type'] = 'datetime';
                                    break;
                                case 'meta': // ['meta', 'title'] || ['meta', 'description']
                                    $type = 'meta_'.$fieldList[1];
                                    $fields['meta']['type'] = 'meta';
                                    $fieldsMap[$key]['field'] = $type;
                                    $fieldsMap[$key]['type'] = $type;
                                    break;
                                case 'translation':
                                    // Regular field
                                    if ($fieldListCount == 3) { // ['translation', 'el', 'field_name']
                                        $fields[$fieldList[2]]['translation'][$fieldList[1]]['value'] = '';
                                        $fieldsMap[$key]['field'] = $fieldList[2];
                                        $fieldsMap[$key]['type'] = 'translation_string';
                                        $fieldsMap[$key]['translation'] = $fieldList[1];
                                    }
                                    // Special fields
                                    if ($fieldListCount > 3) {
                                        // Body
                                        if ($fieldList[2] == 'text_formatted'){ // ['translation', 'el', 'text_formatted', 'body']
                                            $fields[$fieldList[3]]['translation'][$fieldList[1]]['value'] = '';
                                            $fieldsMap[$key]['field'] = $fieldList[3];
                                            $fieldsMap[$key]['type'] = 'translation_text_formatted';
                                            $fieldsMap[$key]['translation'] = $fieldList[1];
                                        }
                                        if ($fieldList[2] == 'text_formatted_summary'){ // ['translation', 'el', 'text_formatted_summary', 'body']
                                            $fields[$fieldList[3]]['translation'][$fieldList[1]]['value'] = '';
                                            $fieldsMap[$key]['field'] = $fieldList[3];
                                            $fieldsMap[$key]['type'] = 'translation_text_formatted_summary';
                                            $fieldsMap[$key]['translation'] = $fieldList[1];
                                        }
                                        // Media alt
                                        // WD-TODO: differentiate between regular image alt
                                        if ($fieldList[2] == 'alt') { // ['translation', 'el', 'alt', 'field_name']
                                            $fields[$fieldList[3]]['translation'][$fieldList[1]]['alt']['value'] = '';
                                            $fieldsMap[$key]['field'] = $fieldList[3];
                                            $fieldsMap[$key]['type'] = 'translation_media_alt';
                                            $fieldsMap[$key]['translation'] = $fieldList[1];
                                        }
                                        // Meta tags
                                        if ($fieldList[2] == 'meta'){ // ['translation', 'el', 'meta', 'title'] || ['translation', 'el', 'meta', 'description']
                                            $type = 'translation_meta_'.$fieldList[3];
                                            $fields['meta']['translation'][$fieldList[1]][$fieldList[3]] = '';
                                            $fieldsMap[$key]['field'] = $type;
                                            $fieldsMap[$key]['type'] = $type;
                                            $fieldsMap[$key]['translation'] = $fieldList[1];
                                        }
                                    }
                                    break;
                            }
                        }
                    }
                    $firstRow = FALSE;
                    continue;
                }

                // Map row data to fields structure
                foreach($data as $key => $fieldData) {
                    $fieldName = $fieldsMap[$key]['field'];
                    $importType = $fieldsMap[$key]['type'];
                    switch ($importType) {
                        case 'string':
                            $fields[$fieldName]['value'] = $fieldData;
                            break;
                        case 'text_formatted':
                            $fields[$fieldName]['value'] = $fieldData;
                            $fields[$fieldName]['format'] = $fieldsMap[$key]['format'];
                            break;
                        case 'text_formatted_summary':
                            $fields[$fieldName]['summary'] = $fieldData;
                            break;
                        case 'entity_reference':
                            $fields[$fieldName]['value'] = $fieldData;
                            break;
                        case 'media_image_uri':
                            $fields[$fieldName]['value'] = $fieldData;
                            break;
                        case 'media_image_alt':
                            $fields[$fieldName]['alt']['value'] = $fieldData;
                            break;
                        case 'taxonomy_term':
                            $fields[$fieldName]['value'] = $fieldData;
                            break;
                        case 'taxonomy_term_auto_create':
                            $fields[$fieldName]['value'] = $fieldData;
                            break;
                        case 'datetime':
                            $fields[$fieldName]['value'] = $fieldData;
                            break;
                        case 'meta_title':
                            $fields['meta']['title'] = $fieldData;
                            break;
                        case 'meta_description':
                            $fields['meta']['description'] = $fieldData;
                            break;
                        case 'translation_string':
                            $lang = $fieldsMap[$key]['translation'];
                            $fields[$fieldName]['translation'][$lang]['value'] = $fieldData;
                            if($fieldData){
                                $languages[$lang] = $lang;
                            }
                            break;
                        case 'translation_text_formatted':
                            $lang = $fieldsMap[$key]['translation'];
                            $fields[$fieldName]['translation'][$lang]['value'] = $fieldData;
                            if($fieldData){
                                $languages[$lang] = $lang;
                            }
                            break;
                        case 'translation_text_formatted_summary':
                            $lang = $fieldsMap[$key]['translation'];
                            $fields[$fieldName]['translation'][$lang]['summary'] = $fieldData;
                            if($fieldData){
                                $languages[$lang] = $lang;
                            }
                            break;
                        case 'translation_media_alt':
                            $lang = $fieldsMap[$key]['translation'];
                            $fields[$fieldName]['translation'][$lang]['alt']['value'] = $fieldData;
                            if($fieldData){
                                $languages[$lang] = $lang;
                            }
                            break;
                        case 'translation_meta_title':
                            $lang = $fieldsMap[$key]['translation'];
                            $fields['meta']['translation'][$lang]['title'] = $fieldData;
                            if($fieldData){
                                $languages[$lang] = $lang;
                            }
                            break;
                        case 'translation_meta_description':
                            $lang = $fieldsMap[$key]['translation'];
                            $fields['meta']['translation'][$lang]['description'] = $fieldData;
                            if($fieldData){
                                $languages[$lang] = $lang;
                            }
                            break;
                    }
                }

                // set default language
                if(isset($fields['language_code']['value'])){
                    $entityLanguage = $fields['language_code']['value'];
                }
                else{
                    $entityLanguage = $defaultLanguage;
                }


                // Create first entity for default language
                /** @var Drupal\Core\Entity\ContentEntityBase $entity */
                $entity = Drupal::entityTypeManager()
                    ->getStorage($entityType)
                    ->create([
                        'type' => $entityBundle,
                        'langcode' => $entityLanguage,
                        'uid' => $uid,
                        'created' =>  Drupal::time()->getRequestTime(),
                        'changed' =>  Drupal::time()->getRequestTime(),
                    ]);
                // Set row data to entity
                foreach($fields as $fieldName => $fieldData){
                    $fieldValue = isset($fieldData['value']) ? trim($fieldData['value']) : '';
                    $fieldValues = explode(',', $fieldValue);
                    $isMultiValue = is_array($fieldValues);
                    $fieldType = $fieldData['type'];
                    if($fieldType == 'meta' || $entity->hasField($fieldName)){
                        switch($fieldType){
                            case 'string':
                                switch($fieldName){
                                    case 'path':
                                        $entity->set($fieldName, ['alias' => $fieldValue]);
                                        break;
                                    case 'post_data':
                                        break;
                                    default:
                                        $entity->set($fieldName, $fieldValue);
                                        break;
                                }
                                break;
                            case 'text_formatted':
                                $arr = [];
                                $arr = ['value' => $fieldValue, 'format' => $fieldData['format']];
                                if(isset($fieldData['summary'])){
                                    $arr['summary'] = $fieldData['summary'];
                                }
                                $entity->set($fieldName, $arr);
                                break;
                            case 'entity_reference':
                                $entity->set($fieldName, ['target_id' => $fieldValue]);
                                break;
                            case 'media_image':
                                // WD-TODO: catch file not existing exceptions ans stuff
                                // WD-TODO: destination is hardcoded, the module does not create directories

                                try {

                                    $hasMultipleImages = strpos($fieldData['value'], ',');

                                    if (!$hasMultipleImages && $fieldData['value']) {
                                        $source = 'public://' . $sourcePath;
                                        $sourceFile = $source . '/' . $fieldData['value'];
                                        $destination = 'public://' . $destinationPath;
                                        $destinationFile = $destination . '/' . $fieldData['value'];
                                        if (Drupal::service('file_system')->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY)) {
                                            $uri = Drupal::service('file_system')->copy($sourceFile, $destinationFile);
                                            $file = Drupal::entityTypeManager()->getStorage('file')->create(['uri' => $uri]);
                                            $file->save();
                                            $mediaImageAlt = trim($fieldData['alt']['value']);
                                            /** @var Media $mediaImage */
                                            $mediaImage = Drupal::entityTypeManager()
                                                ->getStorage('media')
                                                ->create([
                                                    'bundle' => 'image',
                                                    'uid' => $uid,
                                                    'langcode' => $defaultLanguage,
                                                    'field_media_image' => [
                                                        'target_id' => $file->id(),
                                                        'alt' => $mediaImageAlt,
                                                    ],
                                                    'thumbnail' => [
                                                        'target_id' => $file->id(),
                                                        'alt' => $mediaImageAlt,
                                                    ]
                                                ]);
                                            $mediaImage->setName($fieldValue);
                                            $mediaImage->save();
                                            $entity->set($fieldName, ['target_id' => $mediaImage->id()]);
                                        } else {
                                            Drupal::messenger()->addError('Destination directory does not exist or it is not writable');
                                        }
                                    } else {
                                        $images = explode(',', $fieldData['value']);
                                        $first = true;
                                        foreach ($images as $image) {
                                            if (!$image) {
                                                continue;
                                            }
                                            $source = 'public://' . $sourcePath;
                                            $sourceFile = $source . '/' . $image;
                                            $destination = 'public://' . $destinationPath;
                                            $destinationFile = $destination . '/' . $image;
                                            if (Drupal::service('file_system')->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY)) {
                                                try {
                                                    $uri = Drupal::service('file_system')->copy($sourceFile, $destinationFile);
                                                    $file = Drupal::entityTypeManager()->getStorage('file')->create(['uri' => $uri]);
                                                    $file->save();
                                                    // WD-TODO: multiple atl
                                                    // $mediaImageAlt = trim($fieldData['alt']['value']);
                                                    /** @var Media $mediaImage */
                                                    $mediaImage = Drupal::entityTypeManager()
                                                        ->getStorage('media')
                                                        ->create([
                                                            'bundle' => 'image',
                                                            'uid' => $uid,
                                                            'langcode' => $defaultLanguage,
                                                            'field_media_image' => [
                                                                'target_id' => $file->id(),
                                                                'alt' => '',
                                                            ],
                                                            'thumbnail' => [
                                                                'target_id' => $file->id(),
                                                                'alt' => '',
                                                            ]
                                                        ]);
                                                    $mediaImage->setName($image);
                                                    $mediaImage->save();
                                                } catch (\Throwable $e) {
                                                    Drupal::messenger()->addError($e->getMessage());
                                                }
                                                if (isset($mediaImage) && $mediaImage) {
                                                    try {
                                                        if ($first) {
                                                            $entity->set($fieldName, ['target_id' => $mediaImage->id()]);
                                                            $first = false;
                                                        } else {
                                                            $entity->get($fieldName)->appendItem(['target_id' => $mediaImage->id()]);
                                                        }
                                                    } catch (\Throwable $e) {
                                                        Drupal::messenger()->addError($e->getMessage());
                                                    }
                                                }
                                            } else {
                                                Drupal::messenger()->addError('Destination directory does not exist or it is not writable');
                                            }
                                        }
                                    }
                                }
                                catch (\Throwable $e){
                                    Drupal::messenger()->addError($e->getMessage());
                                }
                                break;
                            case 'taxonomy_term':
                                foreach($fieldValues as $v){
                                    // Get term by name, given the vid
                                    $terms = Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
                                        'vid' => trim($fieldData['vid']),
                                        'name' => $v,
                                    ]);
                                    $term = reset($terms);
                                    if($term){
                                        if($isMultiValue){
                                            $entity->$fieldName[] = ['target_id' => $term->id()];
                                        }
                                        else{
                                            $entity->set($fieldName, ['target_id' => $term->id()]);
                                        }
                                    }
                                }
                                break;
                            case 'taxonomy_term_auto_create':
                                foreach($fieldValues as $v){
                                    // Get term by name, given the vid
                                    $terms = Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
                                        'vid' => trim($fieldData['vid']),
                                        'name' => $v,
                                    ]);
                                    $term = reset($terms);
                                    if($term) {
                                        if($isMultiValue){
                                            $entity->$fieldName[] = ['target_id' => $term->id()];
                                        }
                                        else{
                                            $entity->set($fieldName, ['target_id' => $term->id()]);
                                        }
                                    }
                                    else{ // if the term does not exist, create it
                                        $termEntity = Drupal::entityTypeManager()
                                            ->getStorage('taxonomy_term')
                                            ->create([
                                                'name' => $v,
                                                'vid' => trim($fieldData['vid']),
                                            ]);
                                        $termEntity->save();
                                        if($isMultiValue){
                                            $entity->$fieldName[] = ['target_id' => $termEntity->id()];
                                        }
                                        else{
                                            $entity->set($fieldName, ['target_id' => $termEntity->id()]);
                                        }
                                    }

                                }
                                break;
                            case 'datetime':
                                $dt = new DrupalDateTime($fieldValue);
                                $dt->setTimezone(new \DateTimeZone('UTC'));
                                $entity->set($fieldName, $dt->format('Y-m-d\TH:i:s'));
                                break;
                            case 'meta':
                                $arr = [];
                                $arr['title'] = $fieldData['title'];
                                $arr['description'] = $fieldData['description'];
                                $entity->set('field_meta_tags', serialize($arr));
                        }
                    }
                }
                try {
                    $entity->save();
                }
                catch (\Throwable $e){
                    Drupal::messenger()->addError($entity->get('title')->getString().', save entity => '.$e->getMessage());
                    continue;
                }
                // Check for translations
                if(!empty($languages)){
                    foreach($languages as $lang){
                        if($lang == $entityLanguage){
                            continue;
                        }
                        $entity->addTranslation($lang, $entity->toArray());
                        try {
                            $entity->save();
                        }
                        catch (\Throwable $e){
                            Drupal::messenger()->addError($entity->get('title')->getValue().', get translation => '.$e->getMessage());
                            continue;
                        }
                        $translatedEntity = $entity->getTranslation($lang);
                        foreach($fields as $fname => $d){
                            if(isset($d['translation'])){
                                $translatedValue = $d['translation'][$lang]['value'];
                                switch($d['type']){
                                    case 'string':
                                        $translatedEntity->set($fname, $translatedValue);
                                        break;
                                    case 'text_formatted':
                                        $arr = [];
                                        $arr = ['value' => $translatedValue, 'format' => $d['format']];
                                        if(isset($d['summary'])){
                                            $arr['summary'] = $d['translation'][$lang]['summary'];
                                        }
                                        $translatedEntity->set($fname, $arr);
                                        break;
                                    case 'media_image':
                                        $translatedMediaImageAlt = trim($d['translation'][$lang]['alt']['value']);
                                        $mediaImageValues = $mediaImage->toArray();
                                        $mediaImageValues['field_media_image'][0]['alt'] = $translatedMediaImageAlt;
                                        $mediaImage->addTranslation('el', $mediaImageValues);
                                        $mediaImage->save();
                                        break;
                                    case 'meta':
                                        $arr = [];
                                        $arr['title'] = $d['translation'][$lang]['title'];
                                        $arr['description'] = $d['translation'][$lang]['description'];
                                        $translatedEntity->set('field_meta_tags', serialize($arr));
                                }
                            }
                        }
                        try {
                            // check if empty title
                            if(!$translatedEntity->get('title')->getString()){
                                $translatedEntity->set('title', $entity->get('title')->getString());
                            }
                            $translatedEntity->save();
                        }
                        catch (\Throwable $e){
                            Drupal::messenger()->addError($entity->get('title')->getString().', save translation => '.$e->getMessage());
                            continue;
                        }
                    }
                }
            }
        }
        $eof = feof($file_handle);
        if ( $eof )  {
            $context['success'] = TRUE;
        }
        $context['message'] = "Processed " . $context['sandbox']['records'] . " records";
        $context['finished'] = ( $eof || $done ) ? TRUE : FALSE;
    }

    public static function importFinish($success, $results, $operations){
        if ($success) {
            Drupal::messenger()->addMessage('Import completed.');
        }
        else {
            Drupal::messenger()->addError('An error occurred and processing did not complete.');
            $count = count($results);
            $message = "$count items unsuccessfully processed";
            Drupal::messenger()->addMessage($message);
        }
    }
}