const injectThemeVariables = (location, theme) => {

  const injectionPoint = document.querySelector(location)
  if (!theme) {
    return
  }
  if (injectionPoint) {
    //links
    injectionPoint.style.setProperty('--link-color', theme.links.default)
    injectionPoint.style.setProperty('--link-color-hover', theme.links.hover)
    injectionPoint.style.setProperty('--link-color-active', theme.links.active)
    injectionPoint.style.setProperty('--link-color-disabled', theme.links.disabled)

    //primary buttons
    injectionPoint.style.setProperty('--primary-button-background-color', theme.buttons.primary.default.background)
    injectionPoint.style.setProperty('--primary-button-text-color', theme.buttons.primary.default.text)
    injectionPoint.style.setProperty('--primary-button-background-color-hover', theme.buttons.primary.hover.background)
    injectionPoint.style.setProperty('--primary-button-text-color-hover', theme.buttons.primary.hover.text)
    injectionPoint.style.setProperty(
      '--primary-button-background-color-active',
      theme.buttons.primary.active.background
    )
    injectionPoint.style.setProperty('--primary-button-text-color-active', theme.buttons.primary.active.text)
    injectionPoint.style.setProperty(
      '--primary-button-background-color-disabled',
      theme.buttons.primary.disabled.background
    )
    injectionPoint.style.setProperty('--primary-button-text-color-disabled', theme.buttons.primary.disabled.text)

    //secondary buttons
    injectionPoint.style.setProperty('--secondary-button-background-color', theme.buttons.secondary.default.background)
    injectionPoint.style.setProperty('--secondary-button-text-color', theme.buttons.secondary.default.text)
    injectionPoint.style.setProperty(
      '--secondary-button-background-color-hover',
      theme.buttons.secondary.hover.background
    )
    injectionPoint.style.setProperty('--secondary-button-text-color-hover', theme.buttons.secondary.hover.text)
    injectionPoint.style.setProperty(
      '--secondary-button-background-color-active',
      theme.buttons.secondary.active.background
    )
    injectionPoint.style.setProperty('--secondary-button-text-color-active', theme.buttons.secondary.active.text)
    injectionPoint.style.setProperty(
      '--secondary-button-background-color-disabled',
      theme.buttons.secondary.disabled.background
    )
    injectionPoint.style.setProperty('--secondary-button-text-color-disabled', theme.buttons.secondary.disabled.text)

    // Uploader
    if (theme.uploader) {
      injectionPoint.style.setProperty('--uploader-background-color', theme.uploader.background)
      injectionPoint.style.setProperty('--uploader-text-color', theme.uploader.text)
      injectionPoint.style.setProperty('--uploader-header-background-color', theme.uploader.header.background)
      injectionPoint.style.setProperty('--uploader-header-text-color', theme.uploader.header.text)
      injectionPoint.style.setProperty('--uploader-item-background-color', theme.uploader.item.background)
      injectionPoint.style.setProperty('--uploader-item-text-color', theme.uploader.item.text)
    }

    // Panel
    if (theme.panel) {
      injectionPoint.style.setProperty('--panel-background-color', theme.panel.background)
      injectionPoint.style.setProperty('--panel-text-color', theme.panel.text)
      injectionPoint.style.setProperty('--panel-text-color-alt', theme.panel.textAlt)

      injectionPoint.style.setProperty('--panel-item-background-color', theme.panel.item.background)
      injectionPoint.style.setProperty('--panel-item-text-color', theme.panel.item.text)
      injectionPoint.style.setProperty('--panel-item-background-color-hover', theme.panel.item.hover.background)
      injectionPoint.style.setProperty('--panel-item-text-color-hover', theme.panel.item.hover.text)

      injectionPoint.style.setProperty(
        '--panel-item-action-hover-background-color-from',
        theme.panel.item.action.hover.backgroundFrom
      )
      injectionPoint.style.setProperty(
        '--panel-item-action-hover-background-color-to',
        theme.panel.item.action.hover.backgroundTo
      )

      injectionPoint.style.setProperty('--panel-header-background-color', theme.panel.header.background)
      injectionPoint.style.setProperty('--panel-header-text-color', theme.panel.header.text)

      injectionPoint.style.setProperty('--panel-subheader-background-color', theme.panel.subheader.background)
      injectionPoint.style.setProperty('--panel-subheader-text-color', theme.panel.subheader.text)

      injectionPoint.style.setProperty('--panel-nav-item-background-color', theme.panel.nav.item.background)
      injectionPoint.style.setProperty('--panel-nav-item-text-color', theme.panel.nav.item.text)
      injectionPoint.style.setProperty('--panel-nav-item-background-color-hover', theme.panel.nav.item.hover.background)
      injectionPoint.style.setProperty('--panel-nav-item-text-color-hover', theme.panel.nav.item.hover.text)

      injectionPoint.style.setProperty('--panel-section-background-color', theme.panel.section.background)
      injectionPoint.style.setProperty('--panel-section-text-color', theme.panel.section.text)
      injectionPoint.style.setProperty('--panel-section-background-color-alt', theme.panel.section.alt.background)
      injectionPoint.style.setProperty('--panel-section-text-color-alt', theme.panel.section.alt.text)
    }

    // Tabs
    if (theme.tabs) {
      injectionPoint.style.setProperty('--tabs-bar-background-color', theme.tabs.bar.background)
      injectionPoint.style.setProperty('--tabs-tab-background-color', theme.tabs.tab.background)
      injectionPoint.style.setProperty('--tabs-tab-text-color', theme.tabs.tab.text)
      injectionPoint.style.setProperty('--tabs-tab-background-color-hover', theme.tabs.tab.hover.background)
      injectionPoint.style.setProperty('--tabs-tab-text-color-hover', theme.tabs.tab.hover.text)
      injectionPoint.style.setProperty('--tabs-tab-background-color-active', theme.tabs.tab.active.background)
      injectionPoint.style.setProperty('--tabs-tab-text-color-active', theme.tabs.tab.active.text)
    }

    // Table
    if (theme.table) {
      injectionPoint.style.setProperty('--table-background-color', theme.table.background)
      injectionPoint.style.setProperty('--table-text-color', theme.table.text)
      injectionPoint.style.setProperty('--table-header-background-color', theme.table.header.background)
      injectionPoint.style.setProperty('--table-header-text-color', theme.table.header.text)
      injectionPoint.style.setProperty('--table-row-background-color', theme.table.row.background)
      injectionPoint.style.setProperty('--table-row-text-color', theme.table.row.text)
      injectionPoint.style.setProperty('--table-row-background-color-alt', theme.table.row.alt.background)
      injectionPoint.style.setProperty('--table-row-text-color-alt', theme.table.row.alt.text)
    }

    // Input
    if (theme.input) {
      injectionPoint.style.setProperty('--input-background-color', theme.input.background)
      injectionPoint.style.setProperty('--input-text-color', theme.input.text)
      injectionPoint.style.setProperty('--input-border-color', theme.input.border.default)
      injectionPoint.style.setProperty('--input-border-color-hover', theme.input.border.hover)
      injectionPoint.style.setProperty('--input-border-color-focus', theme.input.border.focus)
      injectionPoint.style.setProperty('--input-placeholder-color', theme.input.placeholder)
    }

    // Checkbox
    if (theme.checkbox) {
      injectionPoint.style.setProperty('--checkbox-background-color', theme.checkbox.background)
      injectionPoint.style.setProperty('--checkbox-checked-background-color', theme.checkbox.checked.background)
      injectionPoint.style.setProperty('--checkbox-checked-check-color', theme.checkbox.checked.check)
    }

    // Label
    if (theme.label) {
      injectionPoint.style.setProperty('--label-text-color', theme.label.text)
    }

    // Progress Bar
    if (theme.progressBar) {
      injectionPoint.style.setProperty('--progress-bar-background-color', theme.progressBar.background)
      injectionPoint.style.setProperty('--progress-bar-fill-color', theme.progressBar.fill)
      injectionPoint.style.setProperty('--progress-bar-text-color', theme.progressBar.text)
    }

    // Overlay
    if (theme.overlay) {
      injectionPoint.style.setProperty('--overlay-background-color', theme.overlay.background)
    }

    // Dimensions (if provided)
    if (theme.dimensions) {
      if (theme.dimensions.button) {
        injectionPoint.style.setProperty('--button-height', theme.dimensions.button.height)
        injectionPoint.style.setProperty('--button-width', theme.dimensions.button.width)
        injectionPoint.style.setProperty('--icon-only-button-width', theme.dimensions.button.iconOnlyWidth)
        injectionPoint.style.setProperty('--button-border-radius', theme.dimensions.button.borderRadius)
      }

      if (theme.dimensions.panel) {
        injectionPoint.style.setProperty('--panel-border-radius', theme.dimensions.panel.borderRadius)
      }

      if (theme.dimensions.tabs) {
        injectionPoint.style.setProperty('--tabs-border-radius', theme.dimensions.tabs.borderRadius)
      }

      if (theme.dimensions.settings) {
        injectionPoint.style.setProperty('--settings-width', theme.dimensions.settings.width)
        injectionPoint.style.setProperty('--settings-height', theme.dimensions.settings.height)
      }
    }
  }
}

export default injectThemeVariables
