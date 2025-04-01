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
      //skip 'layout.twig'
      if (basename($template_file, '.twig') === 'layout') {
        continue;
      }
      $templates[] = [
        'name' => basename($template_file, '.twig'),
        'content' => $this->getTemplateContent($template_file),
        'variables' => $this->extractVariables($this->getTemplateContent($template_file)),
        'subject' => $this->getSubject(basename($template_file))
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
      $validator = Validator::make($template, [
        'content' => 'required|string',
        'id' => 'required|string',
        'subject' => 'required|string',
        'variables.action_text' => 'required|string',
        'variables.content' => 'required|string',
        'variables.header' => 'required|string',
      ]);

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
    $subjectSetting = Setting::where('key', 'email_subject_' . $template['id'] . '.twig')->first();
    if ($subjectSetting) {
      $subjectSetting->value = $template['subject'];
      $subjectSetting->save();
    }
    if (!file_exists(dirname($template_file_path))) {
      mkdir(dirname($template_file_path), 0755, true);
    }
    $date = date('Y-m-d H:i:s');
    $template_twig = <<<TWIG
      {% extends 'emails/layout' %}
      {% block header %}{$template['variables']['header']}{% endblock %}
      {% block content %}{$template['variables']['content']}{% endblock %}
      {% block action_url %}{$template['variables']['action_url']}{% endblock %}
      {% block action_text %}{$template['variables']['action_text']}{% endblock %}
      {# file generated at {$date} #}
    TWIG;

    file_put_contents($template_file_path, $template_twig);
  }
}
