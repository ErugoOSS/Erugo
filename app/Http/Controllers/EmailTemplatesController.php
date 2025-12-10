<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Validator;

class EmailTemplatesController extends Controller
{
  public function index()
  {
    //get all email templates (all .twig file in /resources/views/emails)
    $template_files = glob(resource_path('views/emails/*.twig'));
    //load the content of each file
    $templates = [];
    foreach ($template_files as $template_file) {
      $templateName = basename($template_file, '.twig');
      //skip 'layout.twig'
      if ($templateName === 'layout') {
        continue;
      }
      // Strip version suffix (e.g., V2, V3) from display name
      $displayName = preg_replace('/V\d+$/', '', $templateName);
      
      // Get required variables from the original template (not customized)
      $originalContent = file_get_contents($template_file);
      $originalBlocks = $this->extractVariables($originalContent);
      $requiredVariables = array_merge(['subject'], array_keys($originalBlocks));
      
      $templates[] = [
        'name' => $displayName,
        'id' => $templateName,
        'content' => $this->getTemplateContent($template_file),
        'variables' => $this->extractVariables($this->getTemplateContent($template_file)),
        'subject' => $this->getSubject($templateName . '.twig'),
        'requiredVariables' => $requiredVariables
      ];
    }
    return response()->json([
      'status' => 'success',
      'message' => 'Email templates fetched successfully',
      'data' => [
        'templates' => $templates
      ]
    ]);
  }

  private function getTemplateContent($template_file)
  {
    //first see if we have the same filename in storage/templates/emails
    $template_file_path = storage_path('templates/emails/' . basename($template_file));
    if (file_exists($template_file_path)) {
      return file_get_contents($template_file_path);
    }
    return file_get_contents($template_file);
  }

  private function extractVariables($content)
  {
    $variables = [];
    //find content inside blocks like {% block name %}, extract the name and the content
    preg_match_all('/{% block (.*?) %}(.*?){% endblock %}/s', $content, $matches);
    foreach ($matches[1] as $key => $match) {
      $variables[$match] = $this->treatContent($matches[2][$key], $match);
    }
    return $variables;
  }

  private function getSubject($name)
  {
    $setting =  Setting::where('key', 'email_subject_' . $name)->first();
    if ($setting) {
      return $setting->value;
    }
    return null;
  }

  private function treatContent($content, $name)
  {
    if ($name == 'header' || $name == 'action_text' || $name == 'action_url') {
      $content = str_replace("\n", "", $content);
      $content = trim($content);
    }

    if ($name == 'content') {
      //remove leading and trailing newlines
      $content = trim($content, "\n");
    }
    return $content;
  }

  public function update(Request $request)
  {
    $templates = $request->all();
    foreach ($templates as $template) {
      // Build dynamic validation rules based on original template
      $originalPath = resource_path('views/emails/' . $template['id'] . '.twig');
      if (!file_exists($originalPath)) {
        return response()->json([
          'status' => 'error',
          'message' => 'Template not found: ' . $template['id'],
          'data' => []
        ], 404);
      }
      $originalContent = file_get_contents($originalPath);
      $originalBlocks = $this->extractVariables($originalContent);

      $rules = [
        'content' => 'required|string',
        'id' => 'required|string',
        'subject' => 'required|string',
      ];

      // Only require blocks that exist in the original template
      foreach (['header', 'content', 'action_text', 'action_url'] as $block) {
        if (array_key_exists($block, $originalBlocks)) {
          $rules["variables.$block"] = 'required|string';
        }
      }

      $validator = Validator::make($template, $rules);

      if ($validator->fails()) {
        return response()->json([
          'status' => 'error',
          'message' => 'Invalid template',
          'data' => $validator->errors()
        ], 422);
      }

    }

    foreach ($templates as $template) {
      $this->updateTemplate($template);
    }

    return response()->json([
      'status' => 'success',
      'message' => 'Email templates updated successfully',
      'data' => $templates
    ]);
  }

  private function updateTemplate($template)
  {
    $template_file_path = storage_path('templates/emails/' . $template['id'] . '.twig');
    Setting::updateOrCreate(
      ['key' => 'email_subject_' . $template['id'] . '.twig'],
      ['value' => $template['subject'], 'group' => 'system.emails.subjects']
    );
    if (!file_exists(dirname($template_file_path))) {
      mkdir(dirname($template_file_path), 0755, true);
    }
    $date = date('Y-m-d H:i:s');

    // Conditionally build block strings - only include if they exist in the template
    $actionUrlBlock = isset($template['variables']['action_url'])
      ? "{% block action_url %}{$template['variables']['action_url']}{% endblock %}"
      : '';
    $actionTextBlock = isset($template['variables']['action_text'])
      ? "{% block action_text %}{$template['variables']['action_text']}{% endblock %}"
      : '';

    $template_twig = <<<TWIG
      {% extends 'emails/layout' %}
      {% block header %}{$template['variables']['header']}{% endblock %}
      {% block content %}{$template['variables']['content']}{% endblock %}
      {$actionUrlBlock}
      {$actionTextBlock}
      {# file generated at {$date} #}
    TWIG;

    file_put_contents($template_file_path, $template_twig);
  }
}
