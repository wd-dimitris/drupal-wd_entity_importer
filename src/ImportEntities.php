<?php

namespace Drupal\wd_entity_importer;

use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;

class ImportEntities{

    // WD-TODO: Based on field definitions prepare an array for each row and then create node and translations with the values array accordingly

    public function import($csv_uri, $nodeType, &$context){

        $user = \Drupal::currentUser();
        $uid = $user->id();

        $defaultLanguage = \Drupal::languageManager()->getCurrentLanguage()->getId();

        if (!isset($context['sandbox']['offset'])) {
            $context['sandbox']['offset'] = 0;
            $context['sandbox']['records'] = 0;
        }

        $file_handle = fopen($csv_uri, 'r');

        if ( $file_handle === FALSE ) {
            // Failed to open file
            \Drupal::logger('csvImporter')->error('Failed to open '.$csv_uri);
            $context['finished'] = TRUE;
            return;
        }

        $ret = fseek($file_handle, $context['sandbox']['offset']);

        if ( $ret != 0 ) {
            // Failed to seek
            \Drupal::logger('csvImporter')->error('Failed to seek to '.$context['sandbox']['offset']);
            $context['finished'] = TRUE;
            return;
        }

        $limit = 500;  // Maximum number of rows to process at a time
        $done = FALSE;

        $firstRow = TRUE;

        // Get default language
        $languages[$defaultLanguage] = $defaultLanguage;

        $fields = [];
        $fieldsMap = [];
        for ( $i = 0; $i < $limit; $i++ ) {
            $data = fgetcsv($file_handle, 1000, ",");

            if ( $data === FALSE ) {
                $done = TRUE;
                // No more records to process
                break;
            }
            else {
                $context['sandbox']['records']++;
                $context['sandbox']['offset'] = ftell($file_handle);
                $context['sandbox']['records'];
                // Do something with the data

                // Create first node for default language
                /** @var Node $node */
                $node = \Drupal::entityTypeManager()
                    ->getStorage('node')
                    ->create([
                        'type' => $nodeType,
                        'langcode' => $defaultLanguage,
                        'uid' => $uid,
                        'created' => \Drupal::time()->getRequestTime(),
                        'changed' => \Drupal::time()->getRequestTime(),
                    ]);

                // Get first row field info
                if($firstRow){
                    foreach($data as $key => $field) {
                        // $fieldType = $node->get($field)->getFieldDefinition()->getType();
                        // WD-TODO: check if field is translatable
                        //$isTranslatable = $node->get($field)->getFieldDefinition()->isTranslatable();

                        // Regular field
                        if (!strpos($field, '|')) {
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
                                case 'translation':
                                    // Regular field
                                    if ($fieldListCount == 3) { // ['translation', 'el', 'field_name']
                                        $fields[$fieldList[2]]['translation'][$fieldList[1]]['value'] = '';
                                        $fieldsMap[$key]['field'] = $fieldList[2];
                                        $fieldsMap[$key]['type'] = 'translation_string';
                                        $fieldsMap[$key]['translation'] = $fieldList[1];
                                        $languages[$fieldList[1]] = $fieldList[1];
                                    }
                                    // Special fields
                                    if ($fieldListCount > 3) {
                                        // Media alt
                                        // WD-TODO: differentiate between regular image alt
                                        if ($fieldList[2] == 'alt') { // ['translation', 'el', 'alt', 'field_name']
                                            $fields[$fieldList[3]]['translation'][$fieldList[1]]['alt']['value'] = '';
                                            $fieldsMap[$key]['field'] = $fieldList[3];
                                            $fieldsMap[$key]['type'] = 'translation_media_alt';
                                            $fieldsMap[$key]['translation'] = $fieldList[1];
                                            $languages[$fieldList[1]] = $fieldList[1];
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
                        case 'media_image_uri':
                            $fields[$fieldName]['value'] = $fieldData;
                            break;
                        case 'media_image_alt':
                            $fields[$fieldName]['alt']['value'] = $fieldData;
                            break;
                        case 'taxonomy_term':
                            $fields[$fieldName]['value'] = $fieldData;
                            break;
                        case 'translation_string':
                            $lang = $fieldsMap[$key]['translation'];
                            $fields[$fieldName]['translation'][$lang]['value'] = $fieldData;
                            break;
                        case 'translation_media_alt':
                            $lang = $fieldsMap[$key]['translation'];
                            $fields[$fieldName]['translation'][$lang]['alt']['value'] = $fieldData;
                            break;
                    }
                }

                // Set row data to node
                foreach($fields as $fieldName => $fieldData){
                    if($node->hasField($fieldName)){
                        switch($fieldData['type']){
                            case 'string':
                                $node->set($fieldName, $fieldData['value']);
                                break;
                            case 'media_image':
                                // WD-TODO: catch file not existing exceptions ans stuff
                                $uri = \Drupal::service('file_system')->copy('public://wd_entity_importer/images/'.$fieldData['value'], 'public://workservices/'.$fieldData['value']);
                                $file = \Drupal::entityTypeManager()->getStorage('file')->create(['uri' => $uri]);
                                $file->save();
                                /** @var Media $mediaImage */
                                $mediaImage = \Drupal::entityTypeManager()
                                    ->getStorage('media')
                                    ->create([
                                        'bundle' => 'image',
                                        'uid' => $uid,
                                        'langcode' => $defaultLanguage,
                                        'field_media_image' => [
                                            'target_id' => $file->id(),
                                            'alt' => $fieldData['alt']['value'],
                                        ],
                                        'thumbnail' => [
                                            'target_id' => $file->id(),
                                            'alt' => $fieldData['alt']['value'],
                                        ]
                                    ]);
                                $mediaImage->setName($fieldData['value']);
                                $mediaImage->save();
                                $node->set($fieldName, ['target_id' => $mediaImage->id()]);
                                break;
                            case 'taxonomy_term':
                                // Get term by name, given the vid
                                $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
                                    'vid' => $fieldData['vid'],
                                    'name' => $fieldData['value'],
                                ]);
                                $term = reset($terms);
                                if($term){
                                    $node->set($fieldName, ['target_id' => $term->id()]);
                                }
                                break;
                        }
                    }
                }
                $node->save();
                // Check for translations
                if(count($languages) > 1){
                    $skipDefaultLanguage = TRUE;
                    foreach($languages as $lang){
                        if($skipDefaultLanguage){
                            $skipDefaultLanguage = FALSE;
                            continue;
                        }
                        $node->addTranslation($lang, $node->toArray());
                        $node->save();
                        $translatedNode = $node->getTranslation($lang);
                        foreach($fields as $fname => $d){
                            if(isset($d['translation'])){
                                switch($d['type']){
                                    case 'string':
                                        $translatedNode->set($fname, $d['translation'][$lang]['value']);
                                        break;
                                    case 'media_image':
                                        $mediaImageValues = $mediaImage->toArray();
                                        $mediaImageValues['field_media_image'][0]['alt'] = $d['translation'][$lang]['alt']['value'];
                                        $mediaImage->addTranslation('el', $mediaImageValues);
                                        $mediaImage->save();
                                        break;
                                }
                            }
                        }
                        $translatedNode->save();
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

    public function importFinish($success, $results, $operations){
        if ($success) {
            \Drupal::messenger()->addMessage('Import completed.');
        }
        else {
            \Drupal::messenger()->addError('An error occurred and processing did not complete.');
            $count = count($results);
            $message = "$count items uccessfully processed";
            \Drupal::messenger()->addMessage($message);
        }
    }
}