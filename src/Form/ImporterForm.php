<?php

namespace Drupal\wd_entity_importer\Form;

use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ImporterForm.
 */
class ImporterForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'importer_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {

        $validators = [
            'file_validate_extensions' => ['csv'],
        ];

        $contentEntityTypes = [];
        $contentEntityTypesOptions = [];
        $contentEntityBundlesOptions = [];
        $entityTypeDefinitions = \Drupal::entityTypeManager()->getDefinitions();
        foreach ($entityTypeDefinitions as $key => $definition) {
            if ($definition instanceof ContentEntityType) {
                $contentEntityTypes[$key] = $definition;
                $contentEntityTypesOptions[$key] = $definition->getLabel()->getUntranslatedString();
                $contentEntityBundlesOptions[$key] = [];
                $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo($key);
                foreach($bundles as $bundleKey => $bundle){
                    $contentEntityBundlesOptions[$key][$bundleKey] = $bundle['label'];
                }
            }
        }

        $form['entity_type'] = [
            '#type' => 'select',
            '#title' => $this->t('Entity Type'),
            '#options' => $contentEntityTypesOptions,
            '#required' => TRUE,
            '#default_value' => 'node',
            '#ajax' => [
                'callback' => '::entityTypeCallback',
                'event' => 'change',
                'wrapper' => 'entity-type-wrapper',
            ],
        ];

        $form['entity_type_wrapper'] = [
            '#type' => 'container',
            '#attributes' => [
                'id' => 'entity-type-wrapper',
            ],
        ];

        $entityType = $form_state->getValue('entity_type');
        if(empty($entityType)){
            $bundleOptions = $contentEntityBundlesOptions['node'];
        }
        else{
            $bundleOptions = $contentEntityBundlesOptions[$entityType];
        }
        $form['entity_type_wrapper']['entity_bundle'] = [
            '#type' => 'select',
            '#title' => $this->t('Entity Bundle'),
            '#options' => $bundleOptions,
            '#required' => TRUE,
        ];

        $form['csv'] = [
            '#type' => 'managed_file',
            '#title' => t('CSV File'),
            '#description' => t('CSV format only'),
            '#upload_validators' => $validators,
            '#size' => 40,
            '#upload_location' => 'public://wd_entity_importer/csv',
            '#required' => TRUE,
        ];

        $form['source_path'] = [
            '#type' => 'textfield',
            '#title' => t('Files source path'),
            '#description' => t('Path relative to drupal public://, e.g. uploads/images'),
        ];

        $form['destination_path'] = [
            '#type' => 'textfield',
            '#title' => t('Files destination path'),
            '#description' => t('Path relative to drupal public://, e.g. article/images, default=[date:custom:Y]-[date:custom:m]-imported'),
        ];

        $form['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Import'),
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        // WD-TODO: improve validations
        parent::validateForm($form, $form_state);
        $sourcePath = $form_state->getValue('source_path');
        $destinationPath = $form_state->getValue('destination_path');
        if($this->validateFilePath($sourcePath)){
            $form_state->setErrorByName('source_path', "Do not include preceding or trailing slashes.");
        }
        if($this->validateFilePath($destinationPath)){
            $form_state->setErrorByName('destination_path', "Do not include preceding or trailing slashes.");
        }
    }

    public function validateFilePath($path){
        return substr( $path, 0, 1 ) === "/" || substr($path, -1) === '/';
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $trigger = (string) $form_state->getTriggeringElement()['#value'];
        switch ($trigger) {
            case 'Import':
                $entityType = $form_state->getValue('entity_type');
                $entityBundle = $form_state->getValue('entity_bundle');
                $sourcePath = $form_state->getValue('source_path');
                $destinationPath = $form_state->getValue('destination_path');
                $file = $form_state->getValue('csv');
                $csv = \Drupal::entityTypeManager()->getStorage('file')->load($file[0]);
                $csv_uri = $csv->getFileUri();

                $batch = [
                    'operations' => [
                        ['\Drupal\wd_entity_importer\ImportEntities::import', [$csv_uri, $entityType, $entityBundle, $sourcePath, $destinationPath]]
                    ],
                    'finished' => '\Drupal\wd_entity_importer\ImportEntities::importFinish',
                    'title' => $this->t('Importing nodes...'),
                    // We use a single multi-pass operation, so the default
                    // 'Remaining x of y operations' message will be confusing here.
                    'progress_message' => '',
                    'error_message' => $this->t('An error was encountered processing the csv file.'),
                ];
                batch_set($batch);
                return;
        }
        $form_state->setRebuild();
    }

    public function entityTypeCallback(array $form, FormStateInterface $form_state) {
        return $form['entity_type_wrapper'];
    }

}
