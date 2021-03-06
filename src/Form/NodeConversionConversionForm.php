<?php

/**
 * @file
 * Contains \Drupal\node_conversion\Form\NodeConversionConversionForm.
 */

namespace Drupal\node_conversion\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class NodeConversionConversionForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'node_conversion_conversion_form';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state, $node = NULL) {
    $form = [];

    /* Setting the steps */
    if ($form_state->getValue(['step'])) {
      $op = 'choose_destination_type';
    }
    elseif ($form_state->getValue(['step']) == 'choose_destination_type') {
      $op = 'choose_destination_fields';
    }
    $form['step'] = [
      '#type' => 'value',
      '#value' => $op,
    ];
    $form['node'] = [
      '#type' => 'value',
      '#value' => $node,
    ];
    /* Form steps */
    if ($op == 'choose_destination_type') {
      $type = node_type_get_name($node);
      // Remember current node type, used in theme_ function
      $form['current_type'] = [
        '#markup' => $type
        ];
      // Get available content types
      $types = node_conversion_return_access_node_types('to');
      if ($types != FALSE) {
        $key = array_search($form['current_type']['#markup'], $types);
        // Delete the current content type from the list
        if ($key !== FALSE) {
          unset($types[$key]);
        }
        $options = $types;
        // Populate the select with possible content types
        $form['destination_type'] = [
          '#type' => 'select',
          '#options' => $options,
          '#title' => t("To what content type should this node be converted"),
        ];
      }
      else {
        // Just used as a message, not sure if it's the best implementation
        $form['destination_type'] = [
          '#markup' => t("You don't have access to any node types.")
          ];
      }
    }
    elseif ($op == 'choose_destination_fields') {
      // $source_fields = content_types($node->type);
    // Get source type fields
      $source_fields = field_info_instances('node', $node->type);
      // @FIXME
      // Fields and field instances are now exportable configuration entities, and
      // the Field Info API has been removed.
      // 
      // 
      // @see https://www.drupal.org/node/2012896
      // $fields_info = field_info_fields();

      // In case there are no fields, just convert the node type
      if (count($source_fields) == 0) {
        $no_fields = TRUE;
      }
        // Otherwise
      else {
        $no_fields = FALSE;
        // Get destination type fields
        $dest_fields = field_info_instances('node', $form_state->getStorage());
        $i = 0;
        foreach ($source_fields as $source_field_name => $source_field) {
          ++$i;
          $options = [];
          $options['discard'] = 'discard';
          $options[APPEND_TO_BODY] = t('Append to body');
          $options[REPLACE_BODY] = t('Replace body');

          // Insert destination type fields into $options that are of the same type as the source.
          foreach ($dest_fields as $dest_field_name => $dest_value) {
            if ($fields_info[$source_field_name]['type'] == $fields_info[$dest_field_name]['type'] || ($fields_info[$source_field_name]['type'] == 'text_with_summary' && $fields_info[$dest_field_name]['type'] == 'text_long') || ($fields_info[$source_field_name]['type'] == 'text_long' && $fields_info[$dest_field_name]['type'] == 'text_with_summary')) {
              $options[$dest_value['field_name']] = $dest_value['field_name'];
            }
          }
          // Remember the source fields to be converted
          $form['source_field_' . $i] = [
            '#type' => 'value',
            '#value' => $source_field['field_name'],
          ];

          $form['container_' . $i] = [
            '#type' => 'container',
            '#suffix' => '<br />',
          ];

          // The select populated with possible destination fields for each source field
          // If the destination node type has the same field as the source node type, the default value is set to it.
          $form['container_' . $i]['dest_field_' . $i] = [
            '#type' => 'select',
            '#options' => $options,
            '#default_value' => $source_field['field_name'],
            '#title' => \Drupal\Component\Utility\Html::escape($source_field['field_name']) . " " . t("should be inserted into"),
          ];

          // Print the current value of the source field
          $temp_value = node_conversion_format_field_value($node, $fields_info[$source_field_name]);
          $form['container_' . $i]['current_field_value_' . $i] = [
            '#type' => 'item',
            '#markup' => '<div>' . t("Current value is:") . " <b>" . $temp_value . '</b></div>',
          ];
        }
        $form['number_of_fields'] = [
          '#type' => 'value',
          '#value' => $i,
        ];
      }
      $form['no_fields'] = [
        '#type' => 'value',
        '#value' => $no_fields,
      ];

      $hook_options = node_conversion_invoke_all('node_conversion_change', [
        'dest_node_type' => $form_state->getStorage()
        ], 'options');
      if (!empty($hook_options)) {
        $form['hook_options'] = $hook_options;
        array_unshift($form['hook_options'], [
          '#value' => '<br /><strong>' . t("Also the following parameters are available:") . '</strong>'
          ]);
        $form['hook_options']['#tree'] = TRUE;
      }
    }

    if ($op != 'choose_destination_fields' && isset($types) && $types != FALSE) {
      $form['submit'] = [
        '#type' => 'submit',
        '#value' => t("Next"),
      ];
    }
    elseif ($op == 'choose_destination_fields') {
      $form['submit'] = [
        '#type' => 'submit',
        '#value' => t("Convert"),
        '#weight' => 100,
      ];
    }

    return $form;
  }

  public function validateForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    if ($form_state->getValue(['step']) == 'choose_destination_fields') {
      node_conversion_invoke_all('node_conversion_change', [
        'dest_node_type' => $form_state->getStorage(),
        'form_state' => $form_state,
      ], 'options validate');
    }
  }

  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    // Remember the destination type
    if ($form_state->getValue(['step']) == 'choose_destination_type') {
      $form_state->setRebuild(TRUE);
      $form_state->setStorage($form_state->getValue(['destination_type']));
    }
    elseif ($form_state->getValue(['step']) == 'choose_destination_fields') {
      // Information needed in the convert process: nid, vid, source type, destination type
      $dest_node_type = $form_state->getStorage();
      $node = $form_state->getValue(['node']);
      $nid = $node->nid;
      $no_fields = $form_state->getValue(['no_fields']);
      $number_of_fields = !$form_state->getValue(['number_of_fields']) ? $form_state->getValue(['number_of_fields']) : 0;

      // If there are fields that can to be converted.
      $source_fields = [];
      $dest_fields = [];
      if ($no_fields == FALSE) {
        for ($i = 1; $i <= $number_of_fields; $i++) {
          $source_fields[] = $form_state->getValue(['source_field_' . $i]); //  Source fields
          $dest_fields[] = $form_state->getValue(['dest_field_' . $i]); // Destination fields
        }
      }
      if (!empty($form['hook_options'])) {
        $hook_options = $form_state->getValue(['hook_options']);
      }
      else {
        $hook_options = NULL;
      }
      $result = node_conversion_node_conversion($nid, $dest_node_type, $source_fields, $dest_fields, $no_fields, $hook_options);
      // We display errors if any, or the default success message.
      node_conversion_messages($result, [
        'nid' => $nid
        ]);
      // We clear the storage so redirect works
      $form_state->setStorage([]);
      $form_state->set(['redirect'], "node/" . $nid);
    }
  }

}
?>
