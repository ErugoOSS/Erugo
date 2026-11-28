const injectThemeVariables = (location, theme) => {
  const injectionPoint = document.querySelector(location)
  if (!theme) {
    return
  }

  if (injectionPoint) {
    // Themes saved by older versions, or edited by hand, can miss some values:
    // skip them (the defaults of /theme.css stay) instead of throwing, which
    // used to break the settings pages.
    const set = (name, value) => {
      if (value !== undefined && value !== null && value !== '') {
        injectionPoint.style.setProperty(name, value)
      }
    }

    //links
    set('--link-color', theme?.links?.default)
    set('--link-color-hover', theme?.links?.hover)
    set('--link-color-active', theme?.links?.active)
    set('--link-color-disabled', theme?.links?.disabled)

    //primary buttons
    set('--primary-button-background-color', theme?.buttons?.primary?.default?.background)
    set('--primary-button-text-color', theme?.buttons?.primary?.default?.text)
    set('--primary-button-background-color-hover', theme?.buttons?.primary?.hover?.background)
    set('--primary-button-text-color-hover', theme?.buttons?.primary?.hover?.text)
    set(
      '--primary-button-background-color-active',
      theme?.buttons?.primary?.active?.background
    )
    set('--primary-button-text-color-active', theme?.buttons?.primary?.active?.text)
    set(
      '--primary-button-background-color-disabled',
      theme?.buttons?.primary?.disabled?.background
    )
    set('--primary-button-text-color-disabled', theme?.buttons?.primary?.disabled?.text)

    //secondary buttons
    set('--secondary-button-background-color', theme?.buttons?.secondary?.default?.background)
    set('--secondary-button-text-color', theme?.buttons?.secondary?.default?.text)
    set(
      '--secondary-button-background-color-hover',
      theme?.buttons?.secondary?.hover?.background
    )
    set('--secondary-button-text-color-hover', theme?.buttons?.secondary?.hover?.text)
    set(
      '--secondary-button-background-color-active',
      theme?.buttons?.secondary?.active?.background
    )
    set('--secondary-button-text-color-active', theme?.buttons?.secondary?.active?.text)
    set(
      '--secondary-button-background-color-disabled',
      theme?.buttons?.secondary?.disabled?.background
    )
    set('--secondary-button-text-color-disabled', theme?.buttons?.secondary?.disabled?.text)

    //scrollbars
    if (theme?.scrollbars) {
      set('--scrollbar-track-color', theme?.scrollbars?.track)
      set('--scrollbar-thumb-color', theme?.scrollbars?.thumb)
      set('--scrollbar-thumb-hover-color', theme?.scrollbars?.thumbHover)
      set('--scrollbar-width', theme?.scrollbars?.width)
      set('--scrollbar-border-radius', theme?.scrollbars?.borderRadius)
    }

    // Uploader
    if (theme?.uploader) {
      set('--uploader-background-color', theme?.uploader?.background)
      set('--uploader-text-color', theme?.uploader?.text)
      set('--uploader-header-background-color', theme?.uploader?.header?.background)
      set('--uploader-header-text-color', theme?.uploader?.header?.text)
      set('--uploader-item-background-color', theme?.uploader?.item?.background)
      set('--uploader-item-text-color', theme?.uploader?.item?.text)
    }

    // Panel
    if (theme?.panel) {
      set('--panel-background-color', theme?.panel?.background)
      set('--panel-text-color', theme?.panel?.text)
      set('--panel-text-color-alt', theme?.panel?.textAlt)

      set('--panel-item-background-color', theme?.panel?.item?.background)
      set('--panel-item-text-color', theme?.panel?.item?.text)
      set('--panel-item-background-color-hover', theme?.panel?.item?.hover?.background)
      set('--panel-item-text-color-hover', theme?.panel?.item?.hover?.text)

      set(
        '--panel-item-action-hover-background-color-from',
        theme?.panel?.item?.action?.hover?.backgroundFrom
      )
      set(
        '--panel-item-action-hover-background-color-to',
        theme?.panel?.item?.action?.hover?.backgroundTo
      )

      set('--panel-header-background-color', theme?.panel?.header?.background)
      set('--panel-header-text-color', theme?.panel?.header?.text)

      set('--panel-subheader-background-color', theme?.panel?.subheader?.background)
      set('--panel-subheader-text-color', theme?.panel?.subheader?.text)

      set('--panel-nav-item-background-color', theme?.panel?.nav?.item?.background)
      set('--panel-nav-item-text-color', theme?.panel?.nav?.item?.text)
      set('--panel-nav-item-background-color-hover', theme?.panel?.nav?.item?.hover?.background)
      set('--panel-nav-item-text-color-hover', theme?.panel?.nav?.item?.hover?.text)

      set('--panel-section-background-color', theme?.panel?.section?.background)
      set('--panel-section-text-color', theme?.panel?.section?.text)
      set('--panel-section-background-color-alt', theme?.panel?.section?.alt?.background)
      set('--panel-section-text-color-alt', theme?.panel?.section?.alt?.text)
    }

    // Tabs
    if (theme?.tabs) {
      set('--tabs-bar-background-color', theme?.tabs?.bar?.background)
      set('--tabs-tab-background-color', theme?.tabs?.tab?.background)
      set('--tabs-tab-text-color', theme?.tabs?.tab?.text)
      set('--tabs-tab-background-color-hover', theme?.tabs?.tab?.hover?.background)
      set('--tabs-tab-text-color-hover', theme?.tabs?.tab?.hover?.text)
      set('--tabs-tab-background-color-active', theme?.tabs?.tab?.active?.background)
      set('--tabs-tab-text-color-active', theme?.tabs?.tab?.active?.text)
    }

    // Table
    if (theme?.table) {
      set('--table-background-color', theme?.table?.background)
      set('--table-text-color', theme?.table?.text)
      set('--table-header-background-color', theme?.table?.header?.background)
      set('--table-header-text-color', theme?.table?.header?.text)
      set('--table-row-background-color', theme?.table?.row?.background)
      set('--table-row-text-color', theme?.table?.row?.text)
      set('--table-row-background-color-alt', theme?.table?.row?.alt?.background)
      set('--table-row-text-color-alt', theme?.table?.row?.alt?.text)
    }

    // Input
    if (theme?.input) {
      set('--input-background-color', theme?.input?.background)
      set('--input-text-color', theme?.input?.text)
      set('--input-border-color', theme?.input?.border?.default)
      set('--input-border-color-hover', theme?.input?.border?.hover)
      set('--input-border-color-focus', theme?.input?.border?.focus)
      set('--input-placeholder-color', theme?.input?.placeholder)
    }

    // Checkbox
    if (theme?.checkbox) {
      set('--checkbox-background-color', theme?.checkbox?.background)
      set('--checkbox-checked-background-color', theme?.checkbox?.checked?.background)
      set('--checkbox-checked-check-color', theme?.checkbox?.checked?.check)
    }

    // Label
    if (theme?.label) {
      set('--label-text-color', theme?.label?.text)
    }

    // Progress Bar
    if (theme?.progressBar) {
      set('--progress-bar-background-color', theme?.progressBar?.background)
      set('--progress-bar-fill-color', theme?.progressBar?.fill)
      set('--progress-bar-text-color', theme?.progressBar?.text)
    }

    // Overlay
    if (theme?.overlay) {
      set('--overlay-background-color', theme?.overlay?.background)
    }

    // Status Colors
    set('--color-success', theme?.status?.success ?? 'rgb(34, 197, 94)')
    set('--color-warning', theme?.status?.warning ?? 'rgb(234, 179, 8)')
    set('--color-danger', theme?.status?.danger ?? 'rgb(239, 68, 68)')

    // Dimensions (if provided)
    if (theme?.dimensions) {
      if (theme?.dimensions?.button) {
        set('--button-height', theme?.dimensions?.button?.height)
        set('--button-width', theme?.dimensions?.button?.width)
        set('--icon-only-button-width', theme?.dimensions?.button?.iconOnlyWidth)
        set('--button-border-radius', theme?.dimensions?.button?.borderRadius)
      }

      if (theme?.dimensions?.panel) {
        set('--panel-border-radius', theme?.dimensions?.panel?.borderRadius)
      }

      if (theme?.dimensions?.tabs) {
        set('--tabs-border-radius', theme?.dimensions?.tabs?.borderRadius)
      }

      if (theme?.dimensions?.settings) {
        set('--settings-width', theme?.dimensions?.settings?.width)
        set('--settings-height', theme?.dimensions?.settings?.height)
      }
    }
  }
}

export default injectThemeVariables
