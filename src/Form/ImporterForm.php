<?php

namespace Drupal\wd_entity_importer\Form;

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

        $types = \Drupal::entityTypeManager()->getStorage('node_type')->loadMultiple();
        $options = [];
        foreach($types as $type){
            $options[$type->id()] = $type->label();
        }
        $form['node_type'] = [
            '#type' => 'select',
            '#title' => $this->t('Node Type'),
            '#options' => $options,
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

        $form['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Submit'),
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        parent::validateForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $nodeType = $form_state->getValue('node_type');
        $file = $form_state->getValue('csv');
        $csv = \Drupal::entityTypeManager()->getStorage('file')->load($file[0]);
        $csv_uri = $csv->getFileUri();

        $batch = [
            'operations' => [
                ['\Drupal\wd_entity_importer\ImportEntities::import', [$csv_uri, $nodeType]]
            ],
            'finished' => '\Drupal\wd_entity_importer\ImportEntities::importFinish',
            'title' => $this->t('Importing nodes...'),
            // We use a single multi-pass operation, so the default
            // 'Remaining x of y operations' message will be confusing here.
            'progress_message' => '',
            'error_message' => $this->t('An error was encountered processing the csv file.'),
        ];
        batch_set($batch);
    }

}
