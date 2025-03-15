<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.0.2/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <title>{{ $settings['application_name'] }}</title>
  @vite('resources/js/app.js')
 
</head>


<body data-settings='{!! json_encode($settings) !!}' data-error="{{ session()->get('error') }}" data-success="{{ session()->get('success') }}">
  
<style id="erugo-css-variables">
    :root {
      /* Links */
      --link-color: {{ $theme->theme->links->default }};
      --link-color-hover: {{ $theme->theme->links->hover }};
      --link-color-active: {{ $theme->theme->links->active }};
      --link-color-disabled: {{ $theme->theme->links->disabled }};
      
      /* Primary buttons */
      --primary-button-background-color: {{ $theme->theme->buttons->primary->default->background }};
      --primary-button-text-color: {{ $theme->theme->buttons->primary->default->text }};
      --primary-button-background-color-hover: {{ $theme->theme->buttons->primary->hover->background }};
      --primary-button-text-color-hover: {{ $theme->theme->buttons->primary->hover->text }};
      --primary-button-background-color-active: {{ $theme->theme->buttons->primary->active->background }};
      --primary-button-text-color-active: {{ $theme->theme->buttons->primary->active->text }};
      --primary-button-background-color-disabled: {{ $theme->theme->buttons->primary->disabled->background ?? 'rgba(100, 100, 100, 0.566)' }};
      --primary-button-text-color-disabled: {{ $theme->theme->buttons->primary->disabled->text ?? 'rgba(255, 255, 255, 0.579)' }};

      /* Secondary buttons */
      --secondary-button-background-color: {{ $theme->theme->buttons->secondary->default->background }};
      --secondary-button-text-color: {{ $theme->theme->buttons->secondary->default->text }};
      --secondary-button-background-color-hover: {{ $theme->theme->buttons->secondary->hover->background }};
      --secondary-button-text-color-hover: {{ $theme->theme->buttons->secondary->hover->text }};
      --secondary-button-background-color-active: {{ $theme->theme->buttons->secondary->active->background }};
      --secondary-button-text-color-active: {{ $theme->theme->buttons->secondary->active->text }};
      --secondary-button-background-color-disabled: {{ $theme->theme->buttons->secondary->disabled->background ?? 'rgba(100, 100, 100, 0.566)' }};
      --secondary-button-text-color-disabled: {{ $theme->theme->buttons->secondary->disabled->text ?? 'rgba(255, 255, 255, 0.579)' }};
      
      /* Uploader Colors */
      @if(isset($theme->theme->uploader))
      --uploader-background-color: {{ $theme->theme->uploader->background ?? 'rgba(240, 240, 240, 0.769)' }};
      --uploader-text-color: {{ $theme->theme->uploader->text ?? 'rgb(71, 71, 71)' }};
      --uploader-header-background-color: {{ $theme->theme->uploader->header->background ?? 'rgba(245, 245, 245, 0.5)' }};
      --uploader-header-text-color: {{ $theme->theme->uploader->header->text ?? 'rgb(39, 39, 39)' }};
      --uploader-item-background-color: {{ $theme->theme->uploader->item->background ?? 'rgba(255, 255, 255, 0.8)' }};
      --uploader-item-text-color: {{ $theme->theme->uploader->item->text ?? 'rgb(39, 39, 39)' }};
      @endif

      /* Panel Colors */
      @if(isset($theme->theme->panel))
      --panel-background-color: {{ $theme->theme->panel->background ?? 'rgb(235, 235, 235)' }};
      --panel-text-color: {{ $theme->theme->panel->text ?? 'rgb(34, 34, 34)' }};
      --panel-text-color-alt: {{ $theme->theme->panel->textAlt ?? 'rgb(126, 126, 126)' }};
      
      --panel-item-background-color: {{ $theme->theme->panel->item->background ?? 'rgb(215, 215, 215)' }};
      --panel-item-text-color: {{ $theme->theme->panel->item->text ?? 'rgb(34, 34, 34)' }};
      --panel-item-background-color-hover: {{ $theme->theme->panel->item->hover->background ?? 'rgb(238, 238, 238)' }};
      --panel-item-text-color-hover: {{ $theme->theme->panel->item->hover->text ?? 'rgb(34, 34, 34)' }};
      
      --panel-item-action-hover-background-color-from: {{ $theme->theme->panel->item->action->hover->backgroundFrom ?? 'rgba(238, 238, 238, 0.42)' }};
      --panel-item-action-hover-background-color-to: {{ $theme->theme->panel->item->action->hover->backgroundTo ?? 'rgb(238, 238, 238)' }};
      
      --panel-header-background-color: {{ $theme->theme->panel->header->background ?? 'rgba(80, 175, 223, 0.8)' }};
      --panel-header-text-color: {{ $theme->theme->panel->header->text ?? 'rgb(34, 34, 34)' }};
      
      --panel-subheader-background-color: {{ $theme->theme->panel->subheader->background ?? 'linear-gradient(to bottom, rgb(236, 236, 236) 0%, rgb(241, 241, 241) 100%)' }};
      --panel-subheader-text-color: {{ $theme->theme->panel->subheader->text ?? 'rgb(34, 34, 34)' }};
      
      --panel-nav-item-background-color: {{ $theme->theme->panel->nav->item->background ?? 'rgb(223, 223, 223)' }};
      --panel-nav-item-text-color: {{ $theme->theme->panel->nav->item->text ?? 'rgb(53, 53, 53)' }};
      --panel-nav-item-background-color-hover: {{ $theme->theme->panel->nav->item->hover->background ?? 'rgba(80, 175, 223, 0.8)' }};
      --panel-nav-item-text-color-hover: {{ $theme->theme->panel->nav->item->hover->text ?? 'rgb(244, 244, 244)' }};
      
      --panel-section-background-color: {{ $theme->theme->panel->section->background ?? 'rgb(225, 225, 225)' }};
      --panel-section-text-color: {{ $theme->theme->panel->section->text ?? 'rgb(53, 53, 53)' }};
      --panel-section-background-color-alt: {{ $theme->theme->panel->section->alt->background ?? 'rgb(218, 218, 218)' }};
      --panel-section-text-color-alt: {{ $theme->theme->panel->section->alt->text ?? 'rgb(53, 53, 53)' }};
      @endif

      /* Tabs Colors */
      @if(isset($theme->theme->tabs))
      --tabs-bar-background-color: {{ $theme->theme->tabs->bar->background ?? 'rgb(221, 221, 221)' }};
      --tabs-tab-background-color: {{ $theme->theme->tabs->tab->background ?? 'rgb(211, 211, 211)' }};
      --tabs-tab-text-color: {{ $theme->theme->tabs->tab->text ?? 'rgb(53, 53, 53)' }};
      --tabs-tab-background-color-hover: {{ $theme->theme->tabs->tab->hover->background ?? 'rgba(80, 175, 223, 0.8)' }};
      --tabs-tab-text-color-hover: {{ $theme->theme->tabs->tab->hover->text ?? 'rgb(244, 244, 244)' }};
      --tabs-tab-background-color-active: {{ $theme->theme->tabs->tab->active->background ?? 'rgba(80, 175, 223, 0.8)' }};
      --tabs-tab-text-color-active: {{ $theme->theme->tabs->tab->active->text ?? 'rgb(244, 244, 244)' }};
      @endif

      /* Table Colors */
      @if(isset($theme->theme->table))
      --table-background-color: {{ $theme->theme->table->background ?? 'rgb(221, 221, 221)' }};
      --table-text-color: {{ $theme->theme->table->text ?? 'rgb(34, 34, 34)' }};
      --table-header-background-color: {{ $theme->theme->table->header->background ?? 'rgba(80, 175, 223, 1)' }};
      --table-header-text-color: {{ $theme->theme->table->header->text ?? 'rgb(255, 255, 255)' }};
      --table-row-background-color: {{ $theme->theme->table->row->background ?? 'rgb(238, 238, 238)' }};
      --table-row-text-color: {{ $theme->theme->table->row->text ?? 'rgb(34, 34, 34)' }};
      --table-row-background-color-alt: {{ $theme->theme->table->row->alt->background ?? 'rgb(241, 241, 241)' }};
      --table-row-text-color-alt: {{ $theme->theme->table->row->alt->text ?? 'rgb(34, 34, 34)' }};
      @endif

      /* Input Colors */
      @if(isset($theme->theme->input))
      --input-background-color: {{ $theme->theme->input->background ?? 'rgb(236, 236, 236)' }};
      --input-text-color: {{ $theme->theme->input->text ?? 'rgb(34, 34, 34)' }};
      --input-border-color: {{ $theme->theme->input->border->default ?? 'rgb(222, 222, 222)' }};
      --input-border-color-hover: {{ $theme->theme->input->border->hover ?? 'rgb(205, 205, 205)' }};
      --input-border-color-focus: {{ $theme->theme->input->border->focus ?? 'rgb(80, 175, 223)' }};
      --input-placeholder-color: {{ $theme->theme->input->placeholder ?? 'rgb(164, 164, 164)' }};
      @endif

      /* Checkbox Colors */
      @if(isset($theme->theme->checkbox))
      --checkbox-background-color: {{ $theme->theme->checkbox->background ?? 'rgb(236, 236, 236)' }};
      --checkbox-checked-background-color: {{ $theme->theme->checkbox->checked->background ?? 'rgb(80, 175, 223)' }};
      --checkbox-checked-check-color: {{ $theme->theme->checkbox->checked->check ?? 'rgb(255, 255, 255)' }};
      @endif

      /* Label Colors */
      @if(isset($theme->theme->label))
      --label-text-color: {{ $theme->theme->label->text ?? 'rgb(34, 34, 34)' }};
      @endif

      /* Progress Bar Colors */
      @if(isset($theme->theme->progressBar))
      --progress-bar-background-color: {{ $theme->theme->progressBar->background ?? 'rgb(211, 211, 211)' }};
      --progress-bar-fill-color: {{ $theme->theme->progressBar->fill ?? 'rgb(80, 175, 223)' }};
      --progress-bar-text-color: {{ $theme->theme->progressBar->text ?? 'rgb(34, 34, 34)' }};
      @endif

      /* Overlay Colors */
      @if(isset($theme->theme->overlay))
      --overlay-background-color: {{ $theme->theme->overlay->background ?? 'rgba(255, 255, 255, 0.2)' }};
      @endif

      /* Dimensions */
      @if(isset($theme->theme->dimensions))
        @if(isset($theme->theme->dimensions->button))
        --button-height: {{ $theme->theme->dimensions->button->height ?? '50px' }};
        --button-width: {{ $theme->theme->dimensions->button->width ?? '100%' }};
        --icon-only-button-width: {{ $theme->theme->dimensions->button->iconOnlyWidth ?? '50px' }};
        --button-border-radius: {{ $theme->theme->dimensions->button->borderRadius ?? '10px' }};
        @endif

        @if(isset($theme->theme->dimensions->panel))
        --panel-border-radius: {{ $theme->theme->dimensions->panel->borderRadius ?? '10px' }};
        @endif

        @if(isset($theme->theme->dimensions->tabs))
        --tabs-border-radius: {{ $theme->theme->dimensions->tabs->borderRadius ?? '5px 5px 0 0' }};
        @endif

        @if(isset($theme->theme->dimensions->settings))
        --settings-width: {{ $theme->theme->dimensions->settings->width ?? '100vw' }};
        --settings-height: {{ $theme->theme->dimensions->settings->height ?? '100vh' }};
        @endif
      @endif
    }
  </style>
  <div id="app"></div>
</body>

</html>