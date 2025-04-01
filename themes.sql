-- -------------------------------------------------------------
-- v:1.0.1
-- -------------------------------------------------------------
INSERT
  OR IGNORE INTO "themes" (
    "id",
    "name",
    "theme",
    "active",
    "created_at",
    "updated_at",
    "category",
    "bundled"
  )
VALUES
  (
    '10',
    'Erugo',
    '{
  "links": {
    "default": "rgb(57, 115, 144)",
    "hover": "rgb(27, 162, 230)",
    "active": "rgb(70, 154, 196)",
    "disabled": "rgba(190, 190, 190, 0.566)"
  },
  "buttons": {
    "primary": {
      "default": {
        "background": "rgb(138, 208, 242)",
        "text": "rgb(37, 37, 37)"
      },
      "hover": {
        "background": "rgb(70, 154, 196)",
        "text": "rgb(255, 255, 255)"
      },
      "active": {
        "background": "rgb(70, 154, 196)",
        "text": "rgb(255, 255, 255)"
      },
      "disabled": {
        "background": "rgba(100, 100, 100, 0.566)",
        "text": "rgba(255, 255, 255, 0.579)"
      }
    },
    "secondary": {
      "default": {
        "background": "rgb(255, 255, 255)",
        "text": "rgb(34, 34, 34)"
      },
      "hover": {
        "background": "rgb(238, 238, 238)",
        "text": "rgb(34, 34, 34)"
      },
      "active": {
        "background": "rgb(238, 238, 238)",
        "text": "rgb(34, 34, 34)"
      },
      "disabled": {
        "background": "rgba(100, 100, 100, 0.566)",
        "text": "rgba(255, 255, 255, 0.579)"
      }
    }
  },
  "uploader": {
    "background": "rgba(240, 240, 240, 0.769)",
    "text": "rgb(71, 71, 71)",
    "header": {
      "background": "rgba(245, 245, 245, 0.5)",
      "text": "rgb(39, 39, 39)"
    },
    "item": {
      "background": "rgba(255, 255, 255, 0.8)",
      "text": "rgb(39, 39, 39)"
    }
  },
  "panel": {
    "background": "rgb(235, 235, 235)",
    "text": "rgb(34, 34, 34)",
    "item": {
      "background": "rgb(215, 215, 215)",
      "text": "rgb(34, 34, 34)",
      "hover": {
        "background": "rgb(238, 238, 238)",
        "text": "rgb(34, 34, 34)"
      },
      "action": {
        "hover": {
          "backgroundFrom": "rgba(238, 238, 238, 0.42)",
          "backgroundTo": "rgb(238, 238, 238)"
        }
      }
    },
    "header": {
      "background": "rgba(80, 175, 223, 0.8)",
      "text": "rgb(34, 34, 34)"
    },
    "textAlt": "rgb(126, 126, 126)",
    "subheader": {
      "background": "linear-gradient(to bottom, rgb(236, 236, 236) 0%, rgb(241, 241, 241) 100%)",
      "text": "rgb(34, 34, 34)"
    },
    "nav": {
      "item": {
        "background": "rgb(223, 223, 223)",
        "text": "rgb(53, 53, 53)",
        "hover": {
          "background": "rgba(80, 175, 223, 0.8)",
          "text": "rgb(244, 244, 244)"
        }
      }
    },
    "section": {
      "background": "rgb(225, 225, 225)",
      "text": "rgb(53, 53, 53)",
      "alt": {
        "background": "rgb(218, 218, 218)",
        "text": "rgb(53, 53, 53)"
      }
    }
  },
  "tabs": {
    "bar": {
      "background": "rgb(221, 221, 221)"
    },
    "tab": {
      "background": "rgb(211, 211, 211)",
      "text": "rgb(53, 53, 53)",
      "hover": {
        "background": "rgba(80, 175, 223, 0.8)",
        "text": "rgb(244, 244, 244)"
      },
      "active": {
        "background": "rgba(80, 175, 223, 0.8)",
        "text": "rgb(244, 244, 244)"
      }
    }
  },
  "table": {
    "background": "rgb(221, 221, 221)",
    "text": "rgb(34, 34, 34)",
    "header": {
      "background": "rgba(80, 175, 223, 1)",
      "text": "rgb(255, 255, 255)"
    },
    "row": {
      "background": "rgb(238, 238, 238)",
      "text": "rgb(34, 34, 34)",
      "alt": {
        "background": "rgb(241, 241, 241)",
        "text": "rgb(34, 34, 34)"
      }
    }
  },
  "input": {
    "background": "rgb(236, 236, 236)",
    "text": "rgb(34, 34, 34)",
    "border": {
      "default": "rgb(222, 222, 222)",
      "hover": "rgb(205, 205, 205)",
      "focus": "rgb(80, 175, 223)"
    },
    "placeholder": "rgb(164, 164, 164)"
  },
  "checkbox": {
    "background": "rgb(236, 236, 236)",
    "checked": {
      "background": "rgb(80, 175, 223)",
      "check": "rgb(255, 255, 255)"
    }
  },
  "label": {
    "text": "rgb(34, 34, 34)"
  },
  "progressBar": {
    "background": "rgb(211, 211, 211)",
    "fill": "rgb(80, 175, 223)",
    "text": "rgb(34, 34, 34)"
  },
  "overlay": {
    "background": "rgba(255, 255, 255, 0.2)"
  },
  "scrollbars": {
    "track": "rgba(221, 221, 221, 0.4)",
    "thumb": "rgba(138, 208, 242, 0.6)",
    "thumbHover": "rgba(70, 154, 196, 0.8)",
    "width": "6px",
    "borderRadius": "3px"
  },
  "dimensions": {
    "button": {
      "height": "50px",
      "width": "100%",
      "iconOnlyWidth": "50px",
      "borderRadius": "10px"
    },
    "panel": {
      "borderRadius": "10px"
    },
    "tabs": {
      "borderRadius": "5px 5px 0 0"
    },
    "settings": {
      "width": "100vw",
      "height": "100vh"
    }
  }
}
',
    '1',
    '2025-02-27 23:40:21',
    '2025-03-22 10:48:03',
    'Light',
    '1'
  ),
  (
    '12',
    'Heather',
    '{
  "links": {
    "default": "rgb(88, 80, 141)",
    "hover": "rgb(122, 103, 174)",
    "active": "rgb(156, 136, 196)",
    "disabled": "rgba(190, 190, 190, 0.6)"
  },
  "buttons": {
    "primary": {
      "default": {
        "background": "rgb(88, 80, 141)",
        "text": "rgb(255, 255, 255)"
      },
      "hover": {
        "background": "rgb(122, 103, 174)",
        "text": "rgb(255, 255, 255)"
      },
      "active": {
        "background": "rgb(156, 136, 196)",
        "text": "rgb(255, 255, 255)"
      },
      "disabled": {
        "background": "rgba(120, 120, 120, 0.5)",
        "text": "rgba(255, 255, 255, 0.6)"
      }
    },
    "secondary": {
      "default": {
        "background": "rgb(240, 240, 245)",
        "text": "rgb(42, 40, 62)"
      },
      "hover": {
        "background": "rgb(230, 230, 240)",
        "text": "rgb(42, 40, 62)"
      },
      "active": {
        "background": "rgb(220, 220, 235)",
        "text": "rgb(42, 40, 62)"
      },
      "disabled": {
        "background": "rgba(120, 120, 120, 0.5)",
        "text": "rgba(255, 255, 255, 0.6)"
      }
    }
  },
  "uploader": {
    "background": "rgba(245, 245, 250, 0.9)",
    "text": "rgb(42, 40, 62)",
    "header": {
      "background": "rgba(235, 235, 245, 0.7)",
      "text": "rgb(42, 40, 62)"
    },
    "item": {
      "background": "rgba(250, 250, 255, 0.9)",
      "text": "rgb(42, 40, 62)"
    }
  },
  "panel": {
    "background": "rgb(250, 250, 255)",
    "text": "rgb(42, 40, 62)",
    "item": {
      "background": "rgb(240, 240, 245)",
      "text": "rgb(42, 40, 62)",
      "hover": {
        "background": "rgb(230, 230, 240)",
        "text": "rgb(42, 40, 62)"
      },
      "action": {
        "hover": {
          "backgroundFrom": "rgba(230, 230, 240, 0.42)",
          "backgroundTo": "rgb(230, 230, 240)"
        }
      }
    },
    "header": {
      "background": "rgba(88, 80, 141, 0.8)",
      "text": "rgb(255, 255, 255)"
    },
    "textAlt": "rgb(120, 115, 160)",
    "subheader": {
      "background": "linear-gradient(to bottom, rgb(245, 245, 250) 0%, rgb(235, 235, 245) 100%)",
      "text": "rgb(42, 40, 62)"
    },
    "nav": {
      "item": {
        "background": "rgb(240, 240, 245)",
        "text": "rgb(42, 40, 62)",
        "hover": {
          "background": "rgba(88, 80, 141, 0.8)",
          "text": "rgb(255, 255, 255)"
        }
      }
    },
    "section": {
      "background": "rgb(240, 240, 245)",
      "text": "rgb(42, 40, 62)",
      "alt": {
        "background": "rgb(230, 230, 240)",
        "text": "rgb(42, 40, 62)"
      }
    }
  },
  "tabs": {
    "bar": {
      "background": "rgb(235, 235, 245)"
    },
    "tab": {
      "background": "rgb(225, 225, 235)",
      "text": "rgb(42, 40, 62)",
      "hover": {
        "background": "rgba(88, 80, 141, 0.8)",
        "text": "rgb(255, 255, 255)"
      },
      "active": {
        "background": "rgba(88, 80, 141, 0.8)",
        "text": "rgb(255, 255, 255)"
      }
    }
  },
  "table": {
    "background": "rgb(245, 245, 250)",
    "text": "rgb(42, 40, 62)",
    "header": {
      "background": "rgba(88, 80, 141, 1)",
      "text": "rgb(255, 255, 255)"
    },
    "row": {
      "background": "rgb(250, 250, 255)",
      "text": "rgb(42, 40, 62)",
      "alt": {
        "background": "rgb(240, 240, 245)",
        "text": "rgb(42, 40, 62)"
      }
    }
  },
  "input": {
    "background": "rgb(250, 250, 255)",
    "text": "rgb(42, 40, 62)",
    "border": {
      "default": "rgb(200, 195, 230)",
      "hover": "rgb(156, 136, 196)",
      "focus": "rgb(88, 80, 141)"
    },
    "placeholder": "rgb(150, 145, 175)"
  },
  "checkbox": {
    "background": "rgb(250, 250, 255)",
    "checked": {
      "background": "rgb(88, 80, 141)",
      "check": "rgb(255, 255, 255)"
    }
  },
  "label": {
    "text": "rgb(42, 40, 62)"
  },
  "progressBar": {
    "background": "rgb(235, 235, 245)",
    "fill": "rgb(88, 80, 141)",
    "text": "rgb(42, 40, 62)"
  },
  "overlay": {
    "background": "rgba(245, 245, 255, 0.7)"
  },
  "scrollbars": {
    "track": "rgba(235, 235, 245, 0.4)",
    "thumb": "rgba(122, 103, 174, 0.6)",
    "thumbHover": "rgba(156, 136, 196, 0.8)",
    "width": "6px",
    "borderRadius": "3px"
  },
  "dimensions": {
    "button": {
      "height": "50px",
      "width": "100%",
      "iconOnlyWidth": "50px",
      "borderRadius": "10px"
    },
    "panel": {
      "borderRadius": "10px"
    },
    "tabs": {
      "borderRadius": "5px 5px 0 0"
    },
    "settings": {
      "width": "100vw",
      "height": "100vh"
    }
  }
}
',
    '0',
    '2025-02-27 23:40:21',
    '2025-03-22 11:01:07',
    'Light',
    '1'
  ),
  (
    '14',
    'Sakura',
    '{
  "links": {
    "default": "rgb(219, 127, 163)",
    "hover": "rgb(233, 155, 183)",
    "active": "rgb(241, 187, 204)",
    "disabled": "rgba(190, 190, 190, 0.6)"
  },
  "buttons": {
    "primary": {
      "default": {
        "background": "rgb(219, 127, 163)",
        "text": "rgb(255, 255, 255)"
      },
      "hover": {
        "background": "rgb(233, 155, 183)",
        "text": "rgb(255, 255, 255)"
      },
      "active": {
        "background": "rgb(241, 187, 204)",
        "text": "rgb(255, 255, 255)"
      },
      "disabled": {
        "background": "rgba(120, 120, 120, 0.5)",
        "text": "rgba(255, 255, 255, 0.6)"
      }
    },
    "secondary": {
      "default": {
        "background": "rgb(252, 245, 248)",
        "text": "rgb(83, 56, 66)"
      },
      "hover": {
        "background": "rgb(248, 235, 240)",
        "text": "rgb(83, 56, 66)"
      },
      "active": {
        "background": "rgb(244, 226, 233)",
        "text": "rgb(83, 56, 66)"
      },
      "disabled": {
        "background": "rgba(120, 120, 120, 0.5)",
        "text": "rgba(255, 255, 255, 0.6)"
      }
    }
  },
  "uploader": {
    "background": "rgba(252, 240, 245, 0.769)",
    "text": "rgb(83, 56, 66)",
    "header": {
      "background": "rgba(248, 235, 240, 0.5)",
      "text": "rgb(83, 56, 66)"
    },
    "item": {
      "background": "rgba(255, 250, 252, 0.8)",
      "text": "rgb(83, 56, 66)"
    }
  },
  "panel": {
    "background": "rgb(252, 240, 245)",
    "text": "rgb(83, 56, 66)",
    "item": {
      "background": "rgb(248, 235, 240)",
      "text": "rgb(83, 56, 66)",
      "hover": {
        "background": "rgb(244, 226, 233)",
        "text": "rgb(83, 56, 66)"
      },
      "action": {
        "hover": {
          "backgroundFrom": "rgba(244, 226, 233, 0.42)",
          "backgroundTo": "rgb(244, 226, 233)"
        }
      }
    },
    "header": {
      "background": "rgba(219, 127, 163, 0.8)",
      "text": "rgb(255, 255, 255)"
    },
    "textAlt": "rgb(158, 112, 129)",
    "subheader": {
      "background": "linear-gradient(to bottom, rgb(252, 240, 245) 0%, rgb(248, 235, 240) 100%)",
      "text": "rgb(83, 56, 66)"
    },
    "nav": {
      "item": {
        "background": "rgb(248, 235, 240)",
        "text": "rgb(83, 56, 66)",
        "hover": {
          "background": "rgba(219, 127, 163, 0.8)",
          "text": "rgb(255, 255, 255)"
        }
      }
    },
    "section": {
      "background": "rgb(248, 235, 240)",
      "text": "rgb(83, 56, 66)",
      "alt": {
        "background": "rgb(244, 226, 233)",
        "text": "rgb(83, 56, 66)"
      }
    }
  },
  "tabs": {
    "bar": {
      "background": "rgb(244, 226, 233)"
    },
    "tab": {
      "background": "rgb(248, 235, 240)",
      "text": "rgb(83, 56, 66)",
      "hover": {
        "background": "rgba(219, 127, 163, 0.8)",
        "text": "rgb(255, 255, 255)"
      },
      "active": {
        "background": "rgba(219, 127, 163, 0.8)",
        "text": "rgb(255, 255, 255)"
      }
    }
  },
  "table": {
    "background": "rgb(244, 226, 233)",
    "text": "rgb(83, 56, 66)",
    "header": {
      "background": "rgba(219, 127, 163, 1)",
      "text": "rgb(255, 255, 255)"
    },
    "row": {
      "background": "rgb(252, 240, 245)",
      "text": "rgb(83, 56, 66)",
      "alt": {
        "background": "rgb(248, 235, 240)",
        "text": "rgb(83, 56, 66)"
      }
    }
  },
  "input": {
    "background": "rgb(252, 245, 248)",
    "text": "rgb(83, 56, 66)",
    "border": {
      "default": "rgb(241, 187, 204)",
      "hover": "rgb(233, 155, 183)",
      "focus": "rgb(219, 127, 163)"
    },
    "placeholder": "rgb(179, 138, 153)"
  },
  "checkbox": {
    "background": "rgb(252, 245, 248)",
    "checked": {
      "background": "rgb(219, 127, 163)",
      "check": "rgb(255, 255, 255)"
    }
  },
  "label": {
    "text": "rgb(83, 56, 66)"
  },
  "progressBar": {
    "background": "rgb(244, 226, 233)",
    "fill": "rgb(219, 127, 163)",
    "text": "rgb(83, 56, 66)"
  },
  "overlay": {
    "background": "rgba(252, 240, 245, 0.2)"
  },
  "scrollbars": {
    "track": "rgba(244, 226, 233, 0.4)",
    "thumb": "rgba(219, 127, 163, 0.6)",
    "thumbHover": "rgba(233, 155, 183, 0.8)",
    "width": "6px",
    "borderRadius": "3px"
  },
  "dimensions": {
    "button": {
      "height": "50px",
      "width": "100%",
      "iconOnlyWidth": "50px",
      "borderRadius": "10px"
    },
    "panel": {
      "borderRadius": "10px"
    },
    "tabs": {
      "borderRadius": "5px 5px 0 0"
    },
    "settings": {
      "width": "100vw",
      "height": "100vh"
    }
  }
}
',
    '0',
    '2025-02-27 23:40:21',
    '2025-02-28 13:34:56',
    'Light',
    '1'
  ),
  (
    '15',
    'Aqua',
    '{
  "links": {
    "default": "rgb(86, 130, 189)",
    "hover": "rgb(75, 166, 189)",
    "active": "rgb(90, 145, 200)",
    "disabled": "rgba(190, 190, 190, 0.6)"
  },
  "buttons": {
    "primary": {
      "default": {
        "background": "rgb(86, 130, 189)",
        "text": "rgb(255, 255, 255)"
      },
      "hover": {
        "background": "rgb(75, 166, 189)",
        "text": "rgb(255, 255, 255)"
      },
      "active": {
        "background": "rgb(90, 145, 200)",
        "text": "rgb(255, 255, 255)"
      },
      "disabled": {
        "background": "rgba(120, 120, 120, 0.5)",
        "text": "rgba(255, 255, 255, 0.6)"
      }
    },
    "secondary": {
      "default": {
        "background": "rgb(238, 245, 250)",
        "text": "rgb(45, 68, 94)"
      },
      "hover": {
        "background": "rgb(225, 238, 247)",
        "text": "rgb(45, 68, 94)"
      },
      "active": {
        "background": "rgb(213, 232, 245)",
        "text": "rgb(45, 68, 94)"
      },
      "disabled": {
        "background": "rgba(120, 120, 120, 0.5)",
        "text": "rgba(255, 255, 255, 0.6)"
      }
    }
  },
  "uploader": {
    "background": "rgba(232, 245, 252, 0.769)",
    "text": "rgb(45, 68, 94)",
    "header": {
      "background": "rgba(213, 232, 252, 0.5)",
      "text": "rgb(45, 68, 94)"
    },
    "item": {
      "background": "rgba(245, 250, 253, 0.8)",
      "text": "rgb(45, 68, 94)"
    }
  },
  "panel": {
    "background": "rgb(238, 245, 250)",
    "text": "rgb(45, 68, 94)",
    "item": {
      "background": "rgb(225, 238, 247)",
      "text": "rgb(45, 68, 94)",
      "hover": {
        "background": "rgb(213, 232, 245)",
        "text": "rgb(45, 68, 94)"
      },
      "action": {
        "hover": {
          "backgroundFrom": "rgba(213, 232, 245, 0.42)",
          "backgroundTo": "rgb(213, 232, 245)"
        }
      }
    },
    "header": {
      "background": "rgba(129, 199, 212, 0.8)",
      "text": "rgb(255, 255, 255)"
    },
    "textAlt": "rgb(118, 140, 160)",
    "subheader": {
      "background": "linear-gradient(to bottom, rgb(238, 245, 250) 0%, rgb(225, 238, 247) 100%)",
      "text": "rgb(45, 68, 94)"
    },
    "nav": {
      "item": {
        "background": "rgb(225, 238, 247)",
        "text": "rgb(45, 68, 94)",
        "hover": {
          "background": "rgba(129, 199, 212, 0.8)",
          "text": "rgb(255, 255, 255)"
        }
      }
    },
    "section": {
      "background": "rgb(225, 238, 247)",
      "text": "rgb(45, 68, 94)",
      "alt": {
        "background": "rgb(213, 232, 245)",
        "text": "rgb(45, 68, 94)"
      }
    }
  },
  "tabs": {
    "bar": {
      "background": "rgb(213, 232, 245)"
    },
    "tab": {
      "background": "rgb(194, 219, 238)",
      "text": "rgb(45, 68, 94)",
      "hover": {
        "background": "rgba(167, 192, 232, 0.8)",
        "text": "rgb(255, 255, 255)"
      },
      "active": {
        "background": "rgba(129, 199, 212, 0.8)",
        "text": "rgb(255, 255, 255)"
      }
    }
  },
  "table": {
    "background": "rgb(213, 232, 245)",
    "text": "rgb(45, 68, 94)",
    "header": {
      "background": "rgba(129, 199, 212, 1)",
      "text": "rgb(255, 255, 255)"
    },
    "row": {
      "background": "rgb(238, 245, 250)",
      "text": "rgb(45, 68, 94)",
      "alt": {
        "background": "rgb(225, 238, 247)",
        "text": "rgb(45, 68, 94)"
      }
    }
  },
  "input": {
    "background": "rgb(238, 245, 250)",
    "text": "rgb(45, 68, 94)",
    "border": {
      "default": "rgb(194, 219, 238)",
      "hover": "rgb(167, 192, 232)",
      "focus": "rgb(129, 199, 212)"
    },
    "placeholder": "rgb(138, 158, 176)"
  },
  "checkbox": {
    "background": "rgb(238, 245, 250)",
    "checked": {
      "background": "rgb(129, 199, 212)",
      "check": "rgb(255, 255, 255)"
    }
  },
  "label": {
    "text": "rgb(45, 68, 94)"
  },
  "progressBar": {
    "background": "rgb(213, 232, 245)",
    "fill": "linear-gradient(to right, rgb(129, 199, 212), rgb(167, 192, 232), rgb(197, 167, 232))",
    "text": "rgb(45, 68, 94)"
  },
  "overlay": {
    "background": "rgba(213, 232, 245, 0.2)"
  },
  "scrollbars": {
    "track": "rgba(213, 232, 245, 0.4)",
    "thumb": "rgba(129, 199, 212, 0.6)",
    "thumbHover": "rgba(86, 130, 189, 0.8)",
    "width": "6px",
    "borderRadius": "3px"
  },
  "dimensions": {
    "button": {
      "height": "50px",
      "width": "100%",
      "iconOnlyWidth": "50px",
      "borderRadius": "10px"
    },
    "panel": {
      "borderRadius": "10px"
    },
    "tabs": {
      "borderRadius": "5px 5px 0 0"
    },
    "settings": {
      "width": "100vw",
      "height": "100vh"
    }
  }
}
',
    '0',
    '2025-02-27 23:40:21',
    '2025-02-28 14:06:08',
    'Light',
    '1'
  ),
  (
    '17',
    'Colbolt',
    '{
  "links": {
    "default": "rgb(64, 156, 255)",
    "hover": "rgb(96, 175, 255)",
    "active": "rgb(134, 198, 255)",
    "disabled": "rgba(190, 190, 190, 0.4)"
  },
  "buttons": {
    "primary": {
      "default": {
        "background": "rgb(45, 120, 230)",
        "text": "rgb(255, 255, 255)"
      },
      "hover": {
        "background": "rgb(68, 138, 243)",
        "text": "rgb(255, 255, 255)"
      },
      "active": {
        "background": "rgb(96, 154, 243)",
        "text": "rgb(255, 255, 255)"
      },
      "disabled": {
        "background": "rgba(90, 90, 90, 0.5)",
        "text": "rgba(255, 255, 255, 0.4)"
      }
    },
    "secondary": {
      "default": {
        "background": "rgb(44, 44, 52)",
        "text": "rgb(220, 220, 220)"
      },
      "hover": {
        "background": "rgb(56, 56, 66)",
        "text": "rgb(230, 230, 230)"
      },
      "active": {
        "background": "rgb(66, 66, 78)",
        "text": "rgb(240, 240, 240)"
      },
      "disabled": {
        "background": "rgba(50, 50, 50, 0.5)",
        "text": "rgba(200, 200, 200, 0.4)"
      }
    }
  },
  "uploader": {
    "background": "rgba(34, 34, 39, 0.9)",
    "text": "rgb(220, 220, 220)",
    "header": {
      "background": "rgba(44, 44, 52, 0.7)",
      "text": "rgb(230, 230, 230)"
    },
    "item": {
      "background": "rgba(50, 50, 58, 0.8)",
      "text": "rgb(220, 220, 220)"
    }
  },
  "panel": {
    "background": "rgb(24, 24, 28)",
    "text": "rgb(220, 220, 220)",
    "item": {
      "background": "rgb(34, 34, 39)",
      "text": "rgb(220, 220, 220)",
      "hover": {
        "background": "rgb(44, 44, 52)",
        "text": "rgb(230, 230, 230)"
      },
      "action": {
        "hover": {
          "backgroundFrom": "rgba(44, 44, 52, 0.42)",
          "backgroundTo": "rgb(44, 44, 52)"
        }
      }
    },
    "header": {
      "background": "rgba(45, 120, 230, 0.8)",
      "text": "rgb(255, 255, 255)"
    },
    "textAlt": "rgb(170, 170, 170)",
    "subheader": {
      "background": "linear-gradient(to bottom, rgb(34, 34, 39) 0%, rgb(40, 40, 46) 100%)",
      "text": "rgb(220, 220, 220)"
    },
    "nav": {
      "item": {
        "background": "rgb(34, 34, 39)",
        "text": "rgb(220, 220, 220)",
        "hover": {
          "background": "rgba(45, 120, 230, 0.8)",
          "text": "rgb(255, 255, 255)"
        }
      }
    },
    "section": {
      "background": "rgb(34, 34, 39)",
      "text": "rgb(220, 220, 220)",
      "alt": {
        "background": "rgb(40, 40, 46)",
        "text": "rgb(220, 220, 220)"
      }
    }
  },
  "tabs": {
    "bar": {
      "background": "rgb(30, 30, 35)"
    },
    "tab": {
      "background": "rgb(40, 40, 46)",
      "text": "rgb(200, 200, 200)",
      "hover": {
        "background": "rgba(45, 120, 230, 0.8)",
        "text": "rgb(255, 255, 255)"
      },
      "active": {
        "background": "rgba(45, 120, 230, 0.8)",
        "text": "rgb(255, 255, 255)"
      }
    }
  },
  "table": {
    "background": "rgb(28, 28, 32)",
    "text": "rgb(220, 220, 220)",
    "header": {
      "background": "rgba(45, 120, 230, 1)",
      "text": "rgb(255, 255, 255)"
    },
    "row": {
      "background": "rgb(34, 34, 39)",
      "text": "rgb(220, 220, 220)",
      "alt": {
        "background": "rgb(40, 40, 46)",
        "text": "rgb(220, 220, 220)"
      }
    }
  },
  "input": {
    "background": "rgb(40, 40, 46)",
    "text": "rgb(220, 220, 220)",
    "border": {
      "default": "rgb(60, 60, 70)",
      "hover": "rgb(45, 120, 230)",
      "focus": "rgb(96, 154, 243)"
    },
    "placeholder": "rgb(150, 150, 160)"
  },
  "checkbox": {
    "background": "rgb(40, 40, 46)",
    "checked": {
      "background": "rgb(45, 120, 230)",
      "check": "rgb(255, 255, 255)"
    }
  },
  "label": {
    "text": "rgb(220, 220, 220)"
  },
  "progressBar": {
    "background": "rgb(40, 40, 46)",
    "fill": "rgb(45, 120, 230)",
    "text": "rgb(220, 220, 220)"
  },
  "overlay": {
    "background": "rgba(20, 20, 24, 0.8)"
  },
  "scrollbars": {
    "track": "rgba(40, 40, 46, 0.4)",
    "thumb": "rgba(45, 120, 230, 0.6)",
    "thumbHover": "rgba(68, 138, 243, 0.8)",
    "width": "6px",
    "borderRadius": "3px"
  },
  "dimensions": {
    "button": {
      "height": "50px",
      "width": "100%",
      "iconOnlyWidth": "50px",
      "borderRadius": "10px"
    },
    "panel": {
      "borderRadius": "10px"
    },
    "tabs": {
      "borderRadius": "5px 5px 0 0"
    },
    "settings": {
      "width": "100vw",
      "height": "100vh"
    }
  }
}
',
    '0',
    '2025-02-27 23:40:21',
    '2025-02-28 13:04:17',
    'Dark',
    '1'
  ),
  (
    '18',
    'Dracula',
    '{
  "links": {
    "default": "rgb(189, 147, 249)",
    "hover": "rgb(255, 121, 198)",
    "active": "rgb(80, 250, 123)",
    "disabled": "rgba(98, 114, 164, 0.6)"
  },
  "buttons": {
    "primary": {
      "default": {
        "background": "rgb(189, 147, 249)",
        "text": "rgb(40, 42, 54)"
      },
      "hover": {
        "background": "rgb(255, 121, 198)",
        "text": "rgb(40, 42, 54)"
      },
      "active": {
        "background": "rgb(80, 250, 123)",
        "text": "rgb(40, 42, 54)"
      },
      "disabled": {
        "background": "rgba(98, 114, 164, 0.5)",
        "text": "rgba(248, 248, 242, 0.6)"
      }
    },
    "secondary": {
      "default": {
        "background": "rgb(68, 71, 90)",
        "text": "rgb(248, 248, 242)"
      },
      "hover": {
        "background": "rgb(98, 114, 164)",
        "text": "rgb(248, 248, 242)"
      },
      "active": {
        "background": "rgb(139, 233, 253)",
        "text": "rgb(40, 42, 54)"
      },
      "disabled": {
        "background": "rgba(68, 71, 90, 0.5)",
        "text": "rgba(248, 248, 242, 0.6)"
      }
    }
  },
  "uploader": {
    "background": "rgba(40, 42, 54, 0.9)",
    "text": "rgb(248, 248, 242)",
    "header": {
      "background": "rgba(68, 71, 90, 0.7)",
      "text": "rgb(248, 248, 242)"
    },
    "item": {
      "background": "rgba(68, 71, 90, 0.8)",
      "text": "rgb(248, 248, 242)"
    }
  },
  "panel": {
    "background": "rgb(40, 42, 54)",
    "text": "rgb(248, 248, 242)",
    "item": {
      "background": "rgb(68, 71, 90)",
      "text": "rgb(248, 248, 242)",
      "hover": {
        "background": "rgb(98, 114, 164)",
        "text": "rgb(248, 248, 242)"
      },
      "action": {
        "hover": {
          "backgroundFrom": "rgba(98, 114, 164, 0.42)",
          "backgroundTo": "rgb(98, 114, 164)"
        }
      }
    },
    "header": {
      "background": "rgba(189, 147, 249, 0.8)",
      "text": "rgb(40, 42, 54)"
    },
    "textAlt": "rgb(98, 114, 164)",
    "subheader": {
      "background": "linear-gradient(to bottom, rgb(58, 60, 78) 0%, rgb(68, 71, 90) 100%)",
      "text": "rgb(248, 248, 242)"
    },
    "nav": {
      "item": {
        "background": "rgb(68, 71, 90)",
        "text": "rgb(248, 248, 242)",
        "hover": {
          "background": "rgba(189, 147, 249, 0.8)",
          "text": "rgb(40, 42, 54)"
        }
      }
    },
    "section": {
      "background": "rgb(68, 71, 90)",
      "text": "rgb(248, 248, 242)",
      "alt": {
        "background": "rgb(58, 60, 78)",
        "text": "rgb(248, 248, 242)"
      }
    }
  },
  "tabs": {
    "bar": {
      "background": "rgb(40, 42, 54)"
    },
    "tab": {
      "background": "rgb(58, 60, 78)",
      "text": "rgb(248, 248, 242)",
      "hover": {
        "background": "rgba(189, 147, 249, 0.8)",
        "text": "rgb(40, 42, 54)"
      },
      "active": {
        "background": "rgba(189, 147, 249, 0.8)",
        "text": "rgb(40, 42, 54)"
      }
    }
  },
  "table": {
    "background": "rgb(40, 42, 54)",
    "text": "rgb(248, 248, 242)",
    "header": {
      "background": "rgba(189, 147, 249, 1)",
      "text": "rgb(40, 42, 54)"
    },
    "row": {
      "background": "rgb(68, 71, 90)",
      "text": "rgb(248, 248, 242)",
      "alt": {
        "background": "rgb(58, 60, 78)",
        "text": "rgb(248, 248, 242)"
      }
    }
  },
  "input": {
    "background": "rgb(58, 60, 78)",
    "text": "rgb(248, 248, 242)",
    "border": {
      "default": "rgb(98, 114, 164)",
      "hover": "rgb(189, 147, 249)",
      "focus": "rgb(255, 121, 198)"
    },
    "placeholder": "rgb(139, 148, 177)"
  },
  "checkbox": {
    "background": "rgb(58, 60, 78)",
    "checked": {
      "background": "rgb(80, 250, 123)",
      "check": "rgb(40, 42, 54)"
    }
  },
  "label": {
    "text": "rgb(248, 248, 242)"
  },
  "progressBar": {
    "background": "rgb(58, 60, 78)",
    "fill": "rgb(80, 250, 123)",
    "text": "rgb(248, 248, 242)"
  },
  "overlay": {
    "background": "rgba(40, 42, 54, 0.8)"
  },
  "scrollbars": {
    "track": "rgba(58, 60, 78, 0.4)",
    "thumb": "rgba(189, 147, 249, 0.6)",
    "thumbHover": "rgba(255, 121, 198, 0.8)",
    "width": "6px",
    "borderRadius": "3px"
  },
  "dimensions": {
    "button": {
      "height": "50px",
      "width": "100%",
      "iconOnlyWidth": "50px",
      "borderRadius": "10px"
    },
    "panel": {
      "borderRadius": "10px"
    },
    "tabs": {
      "borderRadius": "5px 5px 0 0"
    },
    "settings": {
      "width": "100vw",
      "height": "100vh"
    }
  }
}
',
    '0',
    '2025-02-27 23:40:21',
    '2025-02-28 13:55:00',
    'Dark',
    '1'
  ),
  (
    '19',
    'Github Light',
    '{
  "links": {
    "default": "rgb(9, 105, 218)",
    "hover": "rgb(3, 85, 183)",
    "active": "rgb(1, 76, 166)",
    "disabled": "rgba(140, 149, 159, 0.6)"
  },
  "buttons": {
    "primary": {
      "default": {
        "background": "rgb(31, 136, 61)",
        "text": "rgb(255, 255, 255)"
      },
      "hover": {
        "background": "rgb(22, 125, 52)",
        "text": "rgb(255, 255, 255)"
      },
      "active": {
        "background": "rgb(19, 112, 48)",
        "text": "rgb(255, 255, 255)"
      },
      "disabled": {
        "background": "rgba(31, 136, 61, 0.5)",
        "text": "rgba(255, 255, 255, 0.8)"
      }
    },
    "secondary": {
      "default": {
        "background": "rgb(246, 248, 250)",
        "text": "rgb(36, 41, 47)"
      },
      "hover": {
        "background": "rgb(243, 244, 246)",
        "text": "rgb(36, 41, 47)"
      },
      "active": {
        "background": "rgb(239, 241, 242)",
        "text": "rgb(36, 41, 47)"
      },
      "disabled": {
        "background": "rgba(246, 248, 250, 0.5)",
        "text": "rgba(36, 41, 47, 0.6)"
      }
    }
  },
  "uploader": {
    "background": "rgba(246, 248, 250, 0.9)",
    "text": "rgb(36, 41, 47)",
    "header": {
      "background": "rgba(255, 255, 255, 0.9)",
      "text": "rgb(36, 41, 47)"
    },
    "item": {
      "background": "rgba(255, 255, 255, 0.95)",
      "text": "rgb(36, 41, 47)"
    }
  },
  "panel": {
    "background": "rgb(255, 255, 255)",
    "text": "rgb(36, 41, 47)",
    "item": {
      "background": "rgb(246, 248, 250)",
      "text": "rgb(36, 41, 47)",
      "hover": {
        "background": "rgb(243, 244, 246)",
        "text": "rgb(36, 41, 47)"
      },
      "action": {
        "hover": {
          "backgroundFrom": "rgba(243, 244, 246, 0.42)",
          "backgroundTo": "rgb(243, 244, 246)"
        }
      }
    },
    "header": {
      "background": "rgb(246, 248, 250)",
      "text": "rgb(36, 41, 47)"
    },
    "textAlt": "rgb(87, 96, 106)",
    "subheader": {
      "background": "linear-gradient(to bottom, rgb(246, 248, 250) 0%, rgb(239, 241, 243) 100%)",
      "text": "rgb(36, 41, 47)"
    },
    "nav": {
      "item": {
        "background": "rgb(246, 248, 250)",
        "text": "rgb(36, 41, 47)",
        "hover": {
          "background": "rgb(243, 244, 246)",
          "text": "rgb(9, 105, 218)"
        }
      }
    },
    "section": {
      "background": "rgb(246, 248, 250)",
      "text": "rgb(36, 41, 47)",
      "alt": {
        "background": "rgb(239, 241, 243)",
        "text": "rgb(36, 41, 47)"
      }
    }
  },
  "tabs": {
    "bar": {
      "background": "rgb(236, 239, 241)"
    },
    "tab": {
      "background": "rgb(255, 255, 255)",
      "text": "rgb(36, 41, 47)",
      "hover": {
        "background": "rgb(255, 255, 255)",
        "text": "rgb(9, 105, 218)"
      },
      "active": {
        "background": "rgb(255, 255, 255)",
        "text": "rgb(9, 105, 218)"
      }
    }
  },
  "table": {
    "background": "rgb(255, 255, 255)",
    "text": "rgb(36, 41, 47)",
    "header": {
      "background": "rgb(246, 248, 250)",
      "text": "rgb(36, 41, 47)"
    },
    "row": {
      "background": "rgb(255, 255, 255)",
      "text": "rgb(36, 41, 47)",
      "alt": {
        "background": "rgb(246, 248, 250)",
        "text": "rgb(36, 41, 47)"
      }
    }
  },
  "input": {
    "background": "rgb(255, 255, 255)",
    "text": "rgb(36, 41, 47)",
    "border": {
      "default": "rgb(208, 215, 222)",
      "hover": "rgb(175, 184, 193)",
      "focus": "rgb(9, 105, 218)"
    },
    "placeholder": "rgb(110, 119, 129)"
  },
  "checkbox": {
    "background": "rgb(255, 255, 255)",
    "checked": {
      "background": "rgb(9, 105, 218)",
      "check": "rgb(255, 255, 255)"
    }
  },
  "label": {
    "text": "rgb(36, 41, 47)"
  },
  "progressBar": {
    "background": "rgb(246, 248, 250)",
    "fill": "rgb(9, 105, 218)",
    "text": "rgb(36, 41, 47)"
  },
  "overlay": {
    "background": "rgba(240, 242, 244, 0.8)"
  },
  "scrollbars": {
    "track": "rgba(246, 248, 250, 0.4)",
    "thumb": "rgba(208, 215, 222, 0.6)",
    "thumbHover": "rgba(175, 184, 193, 0.8)",
    "width": "6px",
    "borderRadius": "3px"
  },
  "dimensions": {
    "button": {
      "height": "38px",
      "width": "100%",
      "iconOnlyWidth": "38px",
      "borderRadius": "6px"
    },
    "panel": {
      "borderRadius": "6px"
    },
    "tabs": {
      "borderRadius": "6px 6px 0 0"
    },
    "settings": {
      "width": "100vw",
      "height": "100vh"
    }
  }
}
',
    '0',
    '2025-02-27 23:40:21',
    '2025-02-27 23:40:21',
    'Light',
    '1'
  ),
  (
    '20',
    'Tropical Sunset',
    '{
  "links": {
    "default": "rgb(70, 95, 60)",
    "hover": "rgb(95, 125, 82)",
    "active": "rgb(120, 150, 105)",
    "disabled": "rgba(190, 190, 190, 0.6)"
  },
  "buttons": {
    "primary": {
      "default": {
        "background": "rgb(250, 166, 192)",
        "text": "rgb(48, 54, 43)"
      },
      "hover": {
        "background": "rgb(255, 188, 209)",
        "text": "rgb(48, 54, 43)"
      },
      "active": {
        "background": "rgb(243, 146, 175)",
        "text": "rgb(48, 54, 43)"
      },
      "disabled": {
        "background": "rgba(250, 166, 192, 0.5)",
        "text": "rgba(48, 54, 43, 0.6)"
      }
    },
    "secondary": {
      "default": {
        "background": "rgb(235, 215, 235)",
        "text": "rgb(70, 95, 60)"
      },
      "hover": {
        "background": "rgb(242, 227, 242)",
        "text": "rgb(70, 95, 60)"
      },
      "active": {
        "background": "rgb(228, 203, 228)",
        "text": "rgb(70, 95, 60)"
      },
      "disabled": {
        "background": "rgba(235, 215, 235, 0.5)",
        "text": "rgba(70, 95, 60, 0.6)"
      }
    }
  },
  "uploader": {
    "background": "rgba(245, 235, 245, 0.8)",
    "text": "rgb(48, 54, 43)",
    "header": {
      "background": "rgba(235, 215, 235, 0.8)",
      "text": "rgb(48, 54, 43)"
    },
    "item": {
      "background": "rgba(252, 245, 252, 0.9)",
      "text": "rgb(48, 54, 43)"
    }
  },
  "panel": {
    "background": "rgb(250, 235, 245)",
    "text": "rgb(48, 54, 43)",
    "item": {
      "background": "rgb(242, 224, 238)",
      "text": "rgb(48, 54, 43)",
      "hover": {
        "background": "rgb(235, 215, 235)",
        "text": "rgb(48, 54, 43)"
      },
      "action": {
        "hover": {
          "backgroundFrom": "rgba(235, 215, 235, 0.42)",
          "backgroundTo": "rgb(235, 215, 235)"
        }
      }
    },
    "header": {
      "background": "rgba(250, 166, 192, 0.8)",
      "text": "rgb(48, 54, 43)"
    },
    "textAlt": "rgb(110, 125, 100)",
    "subheader": {
      "background": "linear-gradient(to bottom, rgb(242, 224, 238) 0%, rgb(235, 215, 235) 100%)",
      "text": "rgb(48, 54, 43)"
    },
    "nav": {
      "item": {
        "background": "rgb(242, 224, 238)",
        "text": "rgb(48, 54, 43)",
        "hover": {
          "background": "rgba(250, 166, 192, 0.8)",
          "text": "rgb(48, 54, 43)"
        }
      }
    },
    "section": {
      "background": "rgb(242, 224, 238)",
      "text": "rgb(48, 54, 43)",
      "alt": {
        "background": "rgb(235, 215, 235)",
        "text": "rgb(48, 54, 43)"
      }
    }
  },
  "tabs": {
    "bar": {
      "background": "rgb(235, 215, 235)"
    },
    "tab": {
      "background": "rgb(242, 224, 238)",
      "text": "rgb(48, 54, 43)",
      "hover": {
        "background": "rgba(250, 166, 192, 0.8)",
        "text": "rgb(48, 54, 43)"
      },
      "active": {
        "background": "rgba(250, 166, 192, 0.8)",
        "text": "rgb(48, 54, 43)"
      }
    }
  },
  "table": {
    "background": "rgb(250, 235, 245)",
    "text": "rgb(48, 54, 43)",
    "header": {
      "background": "rgba(250, 166, 192, 1)",
      "text": "rgb(48, 54, 43)"
    },
    "row": {
      "background": "rgb(252, 242, 250)",
      "text": "rgb(48, 54, 43)",
      "alt": {
        "background": "rgb(242, 224, 238)",
        "text": "rgb(48, 54, 43)"
      }
    }
  },
  "input": {
    "background": "rgb(252, 242, 250)",
    "text": "rgb(48, 54, 43)",
    "border": {
      "default": "rgb(235, 215, 235)",
      "hover": "rgb(250, 166, 192)",
      "focus": "rgb(70, 95, 60)"
    },
    "placeholder": "rgb(130, 145, 120)"
  },
  "checkbox": {
    "background": "rgb(252, 242, 250)",
    "checked": {
      "background": "rgb(70, 95, 60)",
      "check": "rgb(252, 242, 250)"
    }
  },
  "label": {
    "text": "rgb(48, 54, 43)"
  },
  "progressBar": {
    "background": "rgb(235, 215, 235)",
    "fill": "rgb(250, 166, 192)",
    "text": "rgb(48, 54, 43)"
  },
  "overlay": {
    "background": "rgba(249, 219, 232, 0.7)"
  },
  "dimensions": {
    "button": {
      "height": "50px",
      "width": "100%",
      "iconOnlyWidth": "50px",
      "borderRadius": "12px"
    },
    "panel": {
      "borderRadius": "12px"
    },
    "tabs": {
      "borderRadius": "6px 6px 0 0"
    },
    "settings": {
      "width": "100vw",
      "height": "100vh"
    }
  }
}',
    '0',
    '2025-02-27 23:40:21',
    '2025-02-27 23:40:21',
    'Light',
    '1'
  ),
  (
    '21',
    'Parma Violet',
    '{
  "links": {
    "default": "rgb(80, 210, 194)",
    "hover": "rgb(111, 232, 216)",
    "active": "rgb(139, 240, 227)",
    "disabled": "rgba(190, 190, 190, 0.6)"
  },
  "buttons": {
    "primary": {
      "default": {
        "background": "linear-gradient(135deg, rgb(108, 99, 255) 0%, rgb(138, 85, 255) 100%)",
        "text": "rgb(255, 255, 255)"
      },
      "hover": {
        "background": "linear-gradient(135deg, rgb(128, 119, 255) 0%, rgb(158, 105, 255) 100%)",
        "text": "rgb(255, 255, 255)"
      },
      "active": {
        "background": "linear-gradient(135deg, rgb(148, 139, 255) 0%, rgb(178, 125, 255) 100%)",
        "text": "rgb(255, 255, 255)"
      },
      "disabled": {
        "background": "linear-gradient(135deg, rgba(108, 99, 255, 0.5) 0%, rgba(138, 85, 255, 0.5) 100%)",
        "text": "rgba(255, 255, 255, 0.7)"
      }
    },
    "secondary": {
      "default": {
        "background": "linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(240, 240, 255, 0.9) 100%)",
        "text": "rgb(62, 65, 99)"
      },
      "hover": {
        "background": "linear-gradient(135deg, rgba(255, 255, 255, 1) 0%, rgba(245, 245, 255, 1) 100%)",
        "text": "rgb(62, 65, 99)"
      },
      "active": {
        "background": "linear-gradient(135deg, rgba(245, 245, 255, 1) 0%, rgba(235, 235, 255, 1) 100%)",
        "text": "rgb(62, 65, 99)"
      },
      "disabled": {
        "background": "linear-gradient(135deg, rgba(255, 255, 255, 0.5) 0%, rgba(240, 240, 255, 0.5) 100%)",
        "text": "rgba(62, 65, 99, 0.5)"
      }
    }
  },
  "uploader": {
    "background": "linear-gradient(160deg, rgba(235, 238, 255, 0.95) 0%, rgba(220, 225, 245, 0.95) 100%)",
    "text": "rgb(62, 65, 99)",
    "header": {
      "background": "linear-gradient(160deg, rgba(245, 248, 255, 0.8) 0%, rgba(235, 238, 255, 0.8) 100%)",
      "text": "rgb(62, 65, 99)"
    },
    "item": {
      "background": "linear-gradient(160deg, rgba(255, 255, 255, 0.9) 0%, rgba(245, 248, 255, 0.9) 100%)",
      "text": "rgb(62, 65, 99)"
    }
  },
  "panel": {
    "background": "linear-gradient(150deg, rgb(240, 242, 255) 0%, rgb(225, 232, 247) 100%)",
    "text": "rgb(62, 65, 99)",
    "item": {
      "background": "linear-gradient(150deg, rgb(230, 235, 250) 0%, rgb(220, 225, 245) 100%)",
      "text": "rgb(62, 65, 99)",
      "hover": {
        "background": "linear-gradient(150deg, rgb(220, 225, 245) 0%, rgb(210, 215, 240) 100%)",
        "text": "rgb(62, 65, 99)"
      },
      "action": {
        "hover": {
          "backgroundFrom": "rgba(220, 225, 245, 0.42)",
          "backgroundTo": "rgba(210, 215, 240, 0.8)"
        }
      }
    },
    "header": {
      "background": "linear-gradient(135deg, rgba(108, 99, 255, 0.85) 0%, rgba(138, 85, 255, 0.85) 100%)",
      "text": "rgb(255, 255, 255)"
    },
    "textAlt": "rgb(120, 125, 160)",
    "subheader": {
      "background": "linear-gradient(to bottom, rgb(225, 230, 250) 0%, rgb(215, 220, 245) 100%)",
      "text": "rgb(62, 65, 99)"
    },
    "nav": {
      "item": {
        "background": "linear-gradient(150deg, rgb(230, 235, 250) 0%, rgb(220, 225, 245) 100%)",
        "text": "rgb(62, 65, 99)",
        "hover": {
          "background": "linear-gradient(135deg, rgba(108, 99, 255, 0.85) 0%, rgba(138, 85, 255, 0.85) 100%)",
          "text": "rgb(255, 255, 255)"
        }
      }
    },
    "section": {
      "background": "linear-gradient(150deg, rgb(230, 235, 250) 0%, rgb(220, 225, 245) 100%)",
      "text": "rgb(62, 65, 99)",
      "alt": {
        "background": "linear-gradient(150deg, rgb(220, 225, 245) 0%, rgb(210, 215, 240) 100%)",
        "text": "rgb(62, 65, 99)"
      }
    }
  },
  "tabs": {
    "bar": {
      "background": "linear-gradient(180deg, rgb(220, 225, 245) 0%, rgb(210, 215, 240) 100%)"
    },
    "tab": {
      "background": "linear-gradient(180deg, rgb(215, 220, 240) 0%, rgb(205, 210, 235) 100%)",
      "text": "rgb(82, 85, 119)",
      "hover": {
        "background": "linear-gradient(135deg, rgba(108, 99, 255, 0.85) 0%, rgba(138, 85, 255, 0.85) 100%)",
        "text": "rgb(255, 255, 255)"
      },
      "active": {
        "background": "linear-gradient(135deg, rgba(108, 99, 255, 0.85) 0%, rgba(138, 85, 255, 0.85) 100%)",
        "text": "rgb(255, 255, 255)"
      }
    }
  },
  "table": {
    "background": "linear-gradient(150deg, rgb(235, 240, 255) 0%, rgb(225, 232, 247) 100%)",
    "text": "rgb(62, 65, 99)",
    "header": {
      "background": "linear-gradient(135deg, rgba(108, 99, 255, 0.9) 0%, rgba(138, 85, 255, 0.9) 100%)",
      "text": "rgb(255, 255, 255)"
    },
    "row": {
      "background": "linear-gradient(150deg, rgb(245, 248, 255) 0%, rgb(235, 242, 252) 100%)",
      "text": "rgb(62, 65, 99)",
      "alt": {
        "background": "linear-gradient(150deg, rgb(235, 240, 255) 0%, rgb(225, 232, 247) 100%)",
        "text": "rgb(62, 65, 99)"
      }
    }
  },
  "input": {
    "background": "linear-gradient(150deg, rgb(250, 252, 255) 0%, rgb(245, 248, 255) 100%)",
    "text": "rgb(62, 65, 99)",
    "border": {
      "default": "rgba(195, 200, 230, 0.8)",
      "hover": "rgba(138, 85, 255, 0.6)",
      "focus": "rgba(108, 99, 255, 0.8)"
    },
    "placeholder": "rgb(150, 155, 185)"
  },
  "checkbox": {
    "background": "linear-gradient(150deg, rgb(250, 252, 255) 0%, rgb(245, 248, 255) 100%)",
    "checked": {
      "background": "linear-gradient(135deg, rgb(108, 99, 255) 0%, rgb(138, 85, 255) 100%)",
      "check": "rgb(255, 255, 255)"
    }
  },
  "label": {
    "text": "rgb(62, 65, 99)"
  },
  "progressBar": {
    "background": "linear-gradient(90deg, rgb(220, 225, 245) 0%, rgb(210, 215, 240) 100%)",
    "fill": "linear-gradient(90deg, rgb(80, 210, 194) 0%, rgb(108, 99, 255) 100%)",
    "text": "rgb(62, 65, 99)"
  },
  "overlay": {
    "background": "linear-gradient(150deg, rgba(235, 240, 255, 0.85) 0%, rgba(225, 232, 247, 0.85) 100%)"
  },
  "scrollbars": {
    "track": "rgba(220, 225, 245, 0.4)",
    "thumb": "rgba(138, 85, 255, 0.5)",
    "thumbHover": "rgba(108, 99, 255, 0.7)",
    "width": "6px",
    "borderRadius": "3px"
  },
  "dimensions": {
    "button": {
      "height": "50px",
      "width": "100%",
      "iconOnlyWidth": "50px",
      "borderRadius": "12px"
    },
    "panel": {
      "borderRadius": "16px"
    },
    "tabs": {
      "borderRadius": "8px 8px 0 0"
    },
    "settings": {
      "width": "100vw",
      "height": "100vh"
    }
  }
}
',
    '0',
    '2025-02-27 23:40:21',
    '2025-03-19 22:14:38',
    'Light',
    '1'
  ),
  (
    '22',
    'CyberNeon Flux',
    '{
  "links": {
    "default": "rgb(0, 255, 205)",
    "hover": "rgb(255, 83, 198)",
    "active": "rgb(255, 245, 60)",
    "disabled": "rgba(75, 75, 95, 0.6)"
  },
  "buttons": {
    "primary": {
      "default": {
        "background": "linear-gradient(90deg, rgb(255, 83, 198) 0%, rgb(198, 0, 245) 100%)",
        "text": "rgb(25, 25, 35)",
        "boxShadow": "0 0 15px rgba(255, 83, 198, 0.7), inset 0 0 10px rgba(255, 83, 198, 0.3)",
        "textShadow": "0 0 5px rgba(255, 255, 255, 0.6)",
        "border": "1px solid rgba(255, 255, 255, 0.2)"
      },
      "hover": {
        "background": "linear-gradient(90deg, rgb(255, 103, 208) 0%, rgb(218, 40, 255) 100%)",
        "text": "rgb(0, 0, 0)",
        "boxShadow": "0 0 20px rgba(255, 83, 198, 0.9), inset 0 0 15px rgba(255, 83, 198, 0.5)",
        "textShadow": "0 0 8px rgba(255, 255, 255, 0.8)",
        "border": "1px solid rgba(255, 255, 255, 0.4)"
      },
      "active": {
        "background": "linear-gradient(90deg, rgb(198, 0, 245) 0%, rgb(255, 83, 198) 100%)",
        "text": "rgb(0, 0, 0)",
        "boxShadow": "0 0 25px rgba(198, 0, 245, 0.9), inset 0 0 10px rgba(255, 255, 255, 0.5)",
        "textShadow": "0 0 10px rgba(255, 255, 255, 1)",
        "border": "1px solid rgba(255, 255, 255, 0.6)"
      },
      "disabled": {
        "background": "linear-gradient(90deg, rgba(255, 83, 198, 0.3) 0%, rgba(198, 0, 245, 0.3) 100%)",
        "text": "rgba(255, 255, 255, 0.4)",
        "boxShadow": "none",
        "textShadow": "none",
        "border": "1px solid rgba(255, 255, 255, 0.1)"
      }
    },
    "secondary": {
      "default": {
        "background": "rgba(30, 30, 45, 0.7)",
        "text": "rgb(0, 255, 205)",
        "boxShadow": "0 0 10px rgba(0, 255, 205, 0.3), inset 0 0 5px rgba(0, 255, 205, 0.1)",
        "textShadow": "0 0 5px rgba(0, 255, 205, 0.5)",
        "border": "1px solid rgba(0, 255, 205, 0.3)"
      },
      "hover": {
        "background": "rgba(40, 40, 60, 0.8)",
        "text": "rgb(50, 255, 215)",
        "boxShadow": "0 0 15px rgba(0, 255, 205, 0.5), inset 0 0 8px rgba(0, 255, 205, 0.2)",
        "textShadow": "0 0 8px rgba(0, 255, 205, 0.7)",
        "border": "1px solid rgba(0, 255, 205, 0.5)"
      },
      "active": {
        "background": "rgba(50, 50, 70, 0.9)",
        "text": "rgb(100, 255, 225)",
        "boxShadow": "0 0 20px rgba(0, 255, 205, 0.6), inset 0 0 10px rgba(0, 255, 205, 0.3)",
        "textShadow": "0 0 10px rgba(0, 255, 205, 0.9)",
        "border": "1px solid rgba(0, 255, 205, 0.7)"
      },
      "disabled": {
        "background": "rgba(30, 30, 45, 0.4)",
        "text": "rgba(0, 255, 205, 0.3)",
        "boxShadow": "none",
        "textShadow": "none",
        "border": "1px solid rgba(0, 255, 205, 0.1)"
      }
    }
  },
  "uploader": {
    "background": "linear-gradient(135deg, rgba(20, 20, 35, 0.85) 0%, rgba(35, 35, 55, 0.85) 100%)",
    "text": "rgb(220, 220, 255)",
    "boxShadow": "0 0 20px rgba(0, 0, 0, 0.6), inset 0 0 20px rgba(0, 255, 205, 0.1)",
    "border": "1px solid rgba(0, 255, 205, 0.2)",
    "backdropFilter": "blur(10px)",
    "header": {
      "background": "linear-gradient(90deg, rgba(30, 30, 50, 0.9) 0%, rgba(50, 30, 70, 0.9) 100%)",
      "text": "rgb(0, 255, 205)",
      "textShadow": "0 0 5px rgba(0, 255, 205, 0.7)",
      "border": "1px solid rgba(255, 83, 198, 0.3)"
    },
    "item": {
      "background": "rgba(40, 40, 60, 0.7)",
      "text": "rgb(200, 200, 255)",
      "border": "1px solid rgba(0, 255, 205, 0.2)",
      "boxShadow": "0 0 10px rgba(0, 0, 0, 0.3), inset 0 0 5px rgba(0, 255, 205, 0.1)"
    }
  },
  "panel": {
    "background": "linear-gradient(135deg, rgba(20, 20, 35, 0.9) 0%, rgba(35, 35, 55, 0.9) 100%)",
    "text": "rgb(220, 220, 255)",
    "boxShadow": "0 0 30px rgba(0, 0, 0, 0.7), inset 0 0 20px rgba(0, 255, 205, 0.07)",
    "border": "1px solid rgba(0, 255, 205, 0.2)",
    "backdropFilter": "blur(5px)",
    "item": {
      "background": "rgba(40, 40, 60, 0.7)",
      "text": "rgb(200, 200, 255)",
      "boxShadow": "0 0 10px rgba(0, 0, 0, 0.3), inset 0 0 5px rgba(0, 255, 205, 0.05)",
      "border": "1px solid rgba(0, 255, 205, 0.15)",
      "hover": {
        "background": "rgba(50, 50, 75, 0.8)",
        "text": "rgb(0, 255, 205)",
        "boxShadow": "0 0 15px rgba(0, 0, 0, 0.4), inset 0 0 10px rgba(0, 255, 205, 0.1)",
        "textShadow": "0 0 5px rgba(0, 255, 205, 0.5)",
        "border": "1px solid rgba(0, 255, 205, 0.3)"
      },
      "action": {
        "hover": {
          "backgroundFrom": "rgba(40, 40, 60, 0.5)",
          "backgroundTo": "rgba(60, 60, 85, 0.8)"
        }
      }
    },
    "header": {
      "background": "linear-gradient(90deg, rgba(255, 83, 198, 0.8) 0%, rgba(198, 0, 245, 0.8) 100%)",
      "text": "rgb(0, 0, 0)",
      "textShadow": "0 0 5px rgba(255, 255, 255, 0.5)",
      "boxShadow": "0 0 15px rgba(255, 83, 198, 0.5)",
      "border": "1px solid rgba(255, 255, 255, 0.3)"
    },
    "textAlt": "rgb(140, 140, 180)",
    "subheader": {
      "background": "linear-gradient(to bottom, rgba(35, 35, 55, 0.9) 0%, rgba(45, 45, 70, 0.9) 100%)",
      "text": "rgb(0, 255, 205)",
      "textShadow": "0 0 3px rgba(0, 255, 205, 0.5)",
      "border": "1px solid rgba(0, 255, 205, 0.2)"
    },
    "nav": {
      "item": {
        "background": "rgba(40, 40, 60, 0.7)",
        "text": "rgb(200, 200, 255)",
        "boxShadow": "inset 0 0 5px rgba(0, 0, 0, 0.3)",
        "border": "1px solid rgba(0, 255, 205, 0.15)",
        "hover": {
          "background": "linear-gradient(90deg, rgba(255, 83, 198, 0.8) 0%, rgba(198, 0, 245, 0.8) 100%)",
          "text": "rgb(0, 0, 0)",
          "boxShadow": "0 0 15px rgba(255, 83, 198, 0.5)",
          "textShadow": "0 0 5px rgba(255, 255, 255, 0.5)",
          "border": "1px solid rgba(255, 255, 255, 0.3)"
        }
      }
    },
    "section": {
      "background": "rgba(40, 40, 60, 0.7)",
      "text": "rgb(200, 200, 255)",
      "border": "1px solid rgba(0, 255, 205, 0.15)",
      "boxShadow": "inset 0 0 10px rgba(0, 0, 0, 0.2)",
      "alt": {
        "background": "rgba(35, 35, 50, 0.7)",
        "text": "rgb(200, 200, 255)",
        "border": "1px solid rgba(0, 255, 205, 0.1)"
      }
    }
  },
  "tabs": {
    "bar": {
      "background": "rgba(25, 25, 40, 0.9)",
      "boxShadow": "0 0 15px rgba(0, 0, 0, 0.5)",
      "border": "1px solid rgba(0, 255, 205, 0.15)"
    },
    "tab": {
      "background": "rgba(40, 40, 60, 0.7)",
      "text": "rgb(160, 160, 200)",
      "border": "1px solid rgba(0, 255, 205, 0.1)",
      "hover": {
        "background": "rgba(50, 50, 75, 0.8)",
        "text": "rgb(0, 255, 205)",
        "textShadow": "0 0 5px rgba(0, 255, 205, 0.5)",
        "border": "1px solid rgba(0, 255, 205, 0.3)"
      },
      "active": {
        "background": "linear-gradient(90deg, rgba(255, 83, 198, 0.8) 0%, rgba(198, 0, 245, 0.8) 100%)",
        "text": "rgb(0, 0, 0)",
        "textShadow": "0 0 5px rgba(255, 255, 255, 0.5)",
        "boxShadow": "0 0 15px rgba(255, 83, 198, 0.5)",
        "border": "1px solid rgba(255, 255, 255, 0.3)"
      }
    }
  },
  "table": {
    "background": "rgba(25, 25, 40, 0.8)",
    "text": "rgb(200, 200, 255)",
    "border": "1px solid rgba(0, 255, 205, 0.2)",
    "boxShadow": "0 0 20px rgba(0, 0, 0, 0.5)",
    "header": {
      "background": "linear-gradient(90deg, rgba(255, 83, 198, 0.8) 0%, rgba(198, 0, 245, 0.8) 100%)",
      "text": "rgb(0, 0, 0)",
      "textShadow": "0 0 5px rgba(255, 255, 255,.4)",
      "border": "1px solid rgba(255, 255, 255, 0.3)"
    },
    "row": {
      "background": "rgba(35, 35, 55, 0.7)",
      "text": "rgb(200, 200, 255)",
      "border": "1px solid rgba(0, 255, 205, 0.1)",
      "alt": {
        "background": "rgba(45, 45, 65, 0.7)",
        "text": "rgb(200, 200, 255)",
        "border": "1px solid rgba(0, 255, 205, 0.1)"
      }
    }
  },
  "input": {
    "background": "rgba(30, 30, 45, 0.7)",
    "text": "rgb(220, 220, 255)",
    "border": {
      "default": "1px solid rgba(0, 255, 205, 0.3)",
      "hover": "1px solid rgba(0, 255, 205, 0.5)",
      "focus": "1px solid rgba(255, 83, 198, 0.7)"
    },
    "boxShadow": {
      "default": "0 0 5px rgba(0, 255, 205, 0.1), inset 0 0 5px rgba(0, 0, 0, 0.2)",
      "hover": "0 0 10px rgba(0, 255, 205, 0.2), inset 0 0 5px rgba(0, 0, 0, 0.2)",
      "focus": "0 0 15px rgba(255, 83, 198, 0.3), inset 0 0 5px rgba(0, 0, 0, 0.2)"
    },
    "placeholder": "rgba(140, 140, 180, 0.8)",
    "textShadow": "0 0 2px rgba(0, 255, 205, 0.3)"
  },
  "checkbox": {
    "background": "rgba(30, 30, 45, 0.7)",
    "border": "1px solid rgba(0, 255, 205, 0.3)",
    "boxShadow": "0 0 5px rgba(0, 255, 205, 0.1), inset 0 0 5px rgba(0, 0, 0, 0.2)",
    "checked": {
      "background": "linear-gradient(45deg, rgb(255, 83, 198) 0%, rgb(198, 0, 245) 100%)",
      "check": "rgb(0, 0, 0)",
      "boxShadow": "0 0 10px rgba(255, 83, 198, 0.5)",
      "border": "1px solid rgba(255, 255, 255, 0.3)"
    }
  },
  "label": {
    "text": "rgb(200, 200, 255)",
    "textShadow": "0 0 2px rgba(0, 255, 205, 0.3)"
  },
  "progressBar": {
    "background": "rgba(30, 30, 45, 0.7)",
    "border": "1px solid rgba(0, 255, 205, 0.2)",
    "boxShadow": "inset 0 0 10px rgba(0, 0, 0, 0.3)",
    "fill": "linear-gradient(90deg, rgb(0, 255, 205) 0%, rgb(255, 83, 198) 50%, rgb(255, 245, 60) 100%)",
    "fillShadow": "0 0 15px rgba(0, 255, 205, 0.5)",
    "text": "rgb(0, 0, 0)",
    "textShadow": "0 0 5px rgba(255, 255, 255, 0.5)"
  },
  "overlay": {
    "background": "linear-gradient(135deg, rgba(20, 20, 35, 0.85) 0%, rgba(35, 35, 55, 0.85) 100%)",
    "backdropFilter": "blur(10px)",
    "boxShadow": "inset 0 0 100px rgba(0, 255, 205, 0.1)"
  },
  "scrollbars": {
    "track": "rgba(25, 25, 40, 0.4)",
    "thumb": "rgba(0, 255, 205, 0.5)",
    "thumbHover": "rgba(255, 83, 198, 0.7)",
    "width": "6px",
    "borderRadius": "3px",
    "boxShadow": "0 0 8px rgba(0, 255, 205, 0.3)",
    "border": "1px solid rgba(0, 255, 205, 0.2)"
  },
  "animations": {
    "button": {
      "hover": "glowPulse 2s infinite alternate",
      "active": "activateGlow 0.3s"
    },
    "nav": {
      "hover": "slideGlow 1.5s infinite alternate"
    }
  },
  "dimensions": {
    "button": {
      "height": "55px",
      "width": "100%",
      "iconOnlyWidth": "55px",
      "borderRadius": "4px"
    },
    "panel": {
      "borderRadius": "6px"
    },
    "tabs": {
      "borderRadius": "6px 6px 0 0"
    },
    "settings": {
      "width": "100vw",
      "height": "100vh"
    }
  }
}
',
    '0',
    '2025-02-27 23:40:21',
    '2025-02-28 18:13:28',
    'Dark',
    '1'
  ),
  (
    '23',
    'Amethyst',
    '{
  "links": {
    "default": "rgb(187, 134, 252)",
    "hover": "rgb(203, 166, 247)",
    "active": "rgb(221, 195, 255)",
    "disabled": "rgba(190, 190, 190, 0.4)"
  },
  "buttons": {
    "primary": {
      "default": {
        "background": "linear-gradient(135deg, rgb(137, 87, 229) 0%, rgb(156, 113, 232) 100%)",
        "text": "rgb(255, 255, 255)",
        "boxShadow": "0 2px 8px rgba(137, 87, 229, 0.3)"
      },
      "hover": {
        "background": "linear-gradient(135deg, rgb(156, 113, 232) 0%, rgb(174, 137, 238) 100%)",
        "text": "rgb(255, 255, 255)",
        "boxShadow": "0 3px 10px rgba(137, 87, 229, 0.4)"
      },
      "active": {
        "background": "linear-gradient(135deg, rgb(174, 137, 238) 0%, rgb(187, 154, 242) 100%)",
        "text": "rgb(255, 255, 255)",
        "boxShadow": "0 2px 6px rgba(137, 87, 229, 0.3)"
      },
      "disabled": {
        "background": "linear-gradient(135deg, rgba(137, 87, 229, 0.4) 0%, rgba(156, 113, 232, 0.4) 100%)",
        "text": "rgba(255, 255, 255, 0.4)",
        "boxShadow": "none"
      }
    },
    "secondary": {
      "default": {
        "background": "linear-gradient(135deg, rgb(44, 44, 52) 0%, rgb(50, 50, 60) 100%)",
        "text": "rgb(220, 220, 220)",
        "boxShadow": "0 2px 6px rgba(0, 0, 0, 0.2)"
      },
      "hover": {
        "background": "linear-gradient(135deg, rgb(56, 56, 66) 0%, rgb(62, 62, 74) 100%)",
        "text": "rgb(230, 230, 230)",
        "boxShadow": "0 3px 8px rgba(0, 0, 0, 0.25)"
      },
      "active": {
        "background": "linear-gradient(135deg, rgb(66, 66, 78) 0%, rgb(72, 72, 86) 100%)",
        "text": "rgb(240, 240, 240)",
        "boxShadow": "0 2px 4px rgba(0, 0, 0, 0.2)"
      },
      "disabled": {
        "background": "linear-gradient(135deg, rgba(50, 50, 50, 0.5) 0%, rgba(60, 60, 60, 0.5) 100%)",
        "text": "rgba(200, 200, 200, 0.4)",
        "boxShadow": "none"
      }
    }
  },
  "uploader": {
    "background": "linear-gradient(150deg, rgba(34, 34, 39, 0.9) 0%, rgba(40, 40, 46, 0.9) 100%)",
    "text": "rgb(220, 220, 220)",
    "boxShadow": "0 4px 12px rgba(0, 0, 0, 0.2)",
    "header": {
      "background": "linear-gradient(180deg, rgba(44, 44, 52, 0.8) 0%, rgba(50, 50, 58, 0.7) 100%)",
      "text": "rgb(230, 230, 230)"
    },
    "item": {
      "background": "linear-gradient(145deg, rgba(50, 50, 58, 0.8) 0%, rgba(56, 56, 64, 0.8) 100%)",
      "text": "rgb(220, 220, 220)",
      "boxShadow": "0 2px 6px rgba(0, 0, 0, 0.15)"
    }
  },
  "panel": {
    "background": "linear-gradient(145deg, rgb(24, 24, 28) 0%, rgb(28, 28, 34) 100%)",
    "text": "rgb(220, 220, 220)",
    "boxShadow": "0 4px 16px rgba(0, 0, 0, 0.3)",
    "item": {
      "background": "linear-gradient(145deg, rgb(34, 34, 39) 0%, rgb(38, 38, 44) 100%)",
      "text": "rgb(220, 220, 220)",
      "boxShadow": "0 2px 4px rgba(0, 0, 0, 0.1)",
      "hover": {
        "background": "linear-gradient(145deg, rgb(44, 44, 52) 0%, rgb(48, 48, 58) 100%)",
        "text": "rgb(230, 230, 230)",
        "boxShadow": "0 2px 6px rgba(0, 0, 0, 0.15)"
      },
      "action": {
        "hover": {
          "backgroundFrom": "rgba(44, 44, 52, 0.42)",
          "backgroundTo": "rgba(52, 52, 62, 0.7)"
        }
      }
    },
    "header": {
      "background": "linear-gradient(135deg, rgba(137, 87, 229, 0.8) 0%, rgba(156, 113, 232, 0.85) 100%)",
      "text": "rgb(255, 255, 255)",
      "boxShadow": "0 2px 8px rgba(137, 87, 229, 0.3)"
    },
    "textAlt": "rgb(170, 170, 170)",
    "subheader": {
      "background": "linear-gradient(to bottom, rgb(34, 34, 39) 0%, rgb(40, 40, 46) 100%)",
      "text": "rgb(220, 220, 220)",
      "boxShadow": "0 1px 4px rgba(0, 0, 0, 0.15)"
    },
    "nav": {
      "item": {
        "background": "linear-gradient(145deg, rgb(34, 34, 39) 0%, rgb(38, 38, 44) 100%)",
        "text": "rgb(220, 220, 220)",
        "hover": {
          "background": "linear-gradient(135deg, rgba(137, 87, 229, 0.8) 0%, rgba(156, 113, 232, 0.85) 100%)",
          "text": "rgb(255, 255, 255)",
          "boxShadow": "0 2px 8px rgba(137, 87, 229, 0.3)"
        }
      }
    },
    "section": {
      "background": "linear-gradient(145deg, rgb(34, 34, 39) 0%, rgb(38, 38, 44) 100%)",
      "text": "rgb(220, 220, 220)",
      "boxShadow": "0 1px 4px rgba(0, 0, 0, 0.1)",
      "alt": {
        "background": "linear-gradient(145deg, rgb(40, 40, 46) 0%, rgb(44, 44, 52) 100%)",
        "text": "rgb(220, 220, 220)",
        "boxShadow": "0 1px 3px rgba(0, 0, 0, 0.08)"
      }
    }
  },
  "tabs": {
    "bar": {
      "background": "linear-gradient(180deg, rgb(30, 30, 35) 0%, rgb(34, 34, 40) 100%)",
      "boxShadow": "0 2px 6px rgba(0, 0, 0, 0.15)"
    },
    "tab": {
      "background": "linear-gradient(180deg, rgb(40, 40, 46) 0%, rgb(44, 44, 52) 100%)",
      "text": "rgb(200, 200, 200)",
      "hover": {
        "background": "linear-gradient(180deg, rgba(137, 87, 229, 0.75) 0%, rgba(156, 113, 232, 0.85) 100%)",
        "text": "rgb(255, 255, 255)",
        "boxShadow": "0 -1px 6px rgba(137, 87, 229, 0.25)"
      },
      "active": {
        "background": "linear-gradient(180deg, rgba(137, 87, 229, 0.8) 0%, rgba(156, 113, 232, 0.9) 100%)",
        "text": "rgb(255, 255, 255)",
        "boxShadow": "0 -1px 6px rgba(137, 87, 229, 0.3)"
      }
    }
  },
  "table": {
    "background": "linear-gradient(145deg, rgb(28, 28, 32) 0%, rgb(32, 32, 38) 100%)",
    "text": "rgb(220, 220, 220)",
    "boxShadow": "0 3px 10px rgba(0, 0, 0, 0.2)",
    "header": {
      "background": "linear-gradient(90deg, rgba(137, 87, 229, 0.95) 0%, rgba(156, 113, 232, 1) 100%)",
      "text": "rgb(255, 255, 255)",
      "boxShadow": "0 1px 5px rgba(137, 87, 229, 0.3)"
    },
    "row": {
      "background": "linear-gradient(145deg, rgb(34, 34, 39) 0%, rgb(38, 38, 44) 100%)",
      "text": "rgb(220, 220, 220)",
      "alt": {
        "background": "linear-gradient(145deg, rgb(40, 40, 46) 0%, rgb(44, 44, 52) 100%)",
        "text": "rgb(220, 220, 220)"
      }
    }
  },
  "input": {
    "background": "linear-gradient(145deg, rgb(38, 38, 44) 0%, rgb(44, 44, 50) 100%)",
    "text": "rgb(220, 220, 220)",
    "boxShadow": "inset 0 1px 3px rgba(0, 0, 0, 0.1)",
    "border": {
      "default": "rgb(60, 60, 70)",
      "hover": "rgb(137, 87, 229)",
      "focus": "rgb(174, 137, 238)"
    },
    "placeholder": "rgb(150, 150, 160)"
  },
  "checkbox": {
    "background": "linear-gradient(145deg, rgb(38, 38, 44) 0%, rgb(44, 44, 50) 100%)",
    "border": "rgb(60, 60, 70)",
    "checked": {
      "background": "linear-gradient(135deg, rgb(137, 87, 229) 0%, rgb(156, 113, 232) 100%)",
      "check": "rgb(255, 255, 255)",
      "boxShadow": "0 1px 3px rgba(137, 87, 229, 0.3)"
    }
  },
  "label": {
    "text": "rgb(220, 220, 220)"
  },
  "progressBar": {
    "background": "linear-gradient(145deg, rgb(38, 38, 44) 0%, rgb(44, 44, 50) 100%)",
    "fill": "linear-gradient(90deg, rgb(137, 87, 229) 0%, rgb(174, 137, 238) 100%)",
    "text": "rgb(220, 220, 220)",
    "boxShadow": "0 1px 4px rgba(0, 0, 0, 0.1)"
  },
  "overlay": {
    "background": "linear-gradient(145deg, rgba(18, 18, 22, 0.85) 0%, rgba(24, 24, 30, 0.85) 100%)",
    "backdropFilter": "blur(3px)"
  },
  "scrollbars": {
    "track": "rgba(34, 34, 39, 0.4)",
    "thumb": "rgba(137, 87, 229, 0.6)",
    "thumbHover": "rgba(156, 113, 232, 0.8)",
    "width": "6px",
    "borderRadius": "3px",
    "boxShadow": "inset 0 1px 3px rgba(0, 0, 0, 0.1)"
  },
  "dimensions": {
    "button": {
      "height": "50px",
      "width": "100%",
      "iconOnlyWidth": "50px",
      "borderRadius": "10px"
    },
    "panel": {
      "borderRadius": "10px"
    },
    "tabs": {
      "borderRadius": "5px 5px 0 0"
    },
    "settings": {
      "width": "100vw",
      "height": "100vh"
    }
  }
}
',
    '0',
    '2025-02-27 23:40:21',
    '2025-02-28 20:44:39',
    'Dark',
    '1'
  ),
  (
    '32',
    'Galaxy',
    '{
  "links": {
    "default": "rgb(80, 210, 194)",
    "hover": "rgb(111, 232, 216)",
    "active": "rgb(139, 240, 227)",
    "disabled": "rgba(80, 80, 80, 0.6)"
  },
  "buttons": {
    "primary": {
      "default": {
        "background": "linear-gradient(135deg, rgb(108, 99, 255) 0%, rgb(138, 85, 255) 100%)",
        "text": "rgb(255, 255, 255)"
      },
      "hover": {
        "background": "linear-gradient(135deg, rgb(128, 119, 255) 0%, rgb(158, 105, 255) 100%)",
        "text": "rgb(255, 255, 255)"
      },
      "active": {
        "background": "linear-gradient(135deg, rgb(148, 139, 255) 0%, rgb(178, 125, 255) 100%)",
        "text": "rgb(255, 255, 255)"
      },
      "disabled": {
        "background": "linear-gradient(135deg, rgba(108, 99, 255, 0.5) 0%, rgba(138, 85, 255, 0.5) 100%)",
        "text": "rgba(255, 255, 255, 0.7)"
      }
    },
    "secondary": {
      "default": {
        "background": "linear-gradient(135deg, rgba(60, 60, 75, 0.9) 0%, rgba(50, 50, 65, 0.9) 100%)",
        "text": "rgb(220, 220, 240)"
      },
      "hover": {
        "background": "linear-gradient(135deg, rgba(70, 70, 85, 1) 0%, rgba(60, 60, 75, 1) 100%)",
        "text": "rgb(230, 230, 250)"
      },
      "active": {
        "background": "linear-gradient(135deg, rgba(80, 80, 95, 1) 0%, rgba(70, 70, 85, 1) 100%)",
        "text": "rgb(240, 240, 255)"
      },
      "disabled": {
        "background": "linear-gradient(135deg, rgba(50, 50, 65, 0.5) 0%, rgba(40, 40, 55, 0.5) 100%)",
        "text": "rgba(200, 200, 220, 0.5)"
      }
    }
  },
  "uploader": {
    "background": "linear-gradient(160deg, rgba(40, 42, 55, 0.95) 0%, rgba(35, 37, 50, 0.95) 100%)",
    "text": "rgb(220, 220, 240)",
    "header": {
      "background": "linear-gradient(160deg, rgba(45, 47, 60, 0.8) 0%, rgba(40, 42, 55, 0.8) 100%)",
      "text": "rgb(230, 230, 250)"
    },
    "item": {
      "background": "linear-gradient(160deg, rgba(50, 52, 65, 0.9) 0%, rgba(45, 47, 60, 0.9) 100%)",
      "text": "rgb(220, 220, 240)"
    }
  },
  "panel": {
    "background": "linear-gradient(150deg, rgb(30, 32, 45) 0%, rgb(25, 27, 40) 100%)",
    "text": "rgb(220, 220, 240)",
    "item": {
      "background": "linear-gradient(150deg, rgb(40, 42, 55) 0%, rgb(35, 37, 50) 100%)",
      "text": "rgb(220, 220, 240)",
      "hover": {
        "background": "linear-gradient(150deg, rgb(50, 52, 65) 0%, rgb(45, 47, 60) 100%)",
        "text": "rgb(230, 230, 250)"
      },
      "action": {
        "hover": {
          "backgroundFrom": "rgba(50, 52, 65, 0.42)",
          "backgroundTo": "rgba(45, 47, 60, 0.8)"
        }
      }
    },
    "header": {
      "background": "linear-gradient(135deg, rgba(108, 99, 255, 0.85) 0%, rgba(138, 85, 255, 0.85) 100%)",
      "text": "rgb(255, 255, 255)"
    },
    "textAlt": "rgb(170, 170, 190)",
    "subheader": {
      "background": "linear-gradient(to bottom, rgb(45, 47, 60) 0%, rgb(40, 42, 55) 100%)",
      "text": "rgb(220, 220, 240)"
    },
    "nav": {
      "item": {
        "background": "linear-gradient(150deg, rgb(40, 42, 55) 0%, rgb(35, 37, 50) 100%)",
        "text": "rgb(220, 220, 240)",
        "hover": {
          "background": "linear-gradient(135deg, rgba(108, 99, 255, 0.85) 0%, rgba(138, 85, 255, 0.85) 100%)",
          "text": "rgb(255, 255, 255)"
        }
      }
    },
    "section": {
      "background": "linear-gradient(150deg, rgb(40, 42, 55) 0%, rgb(35, 37, 50) 100%)",
      "text": "rgb(220, 220, 240)",
      "alt": {
        "background": "linear-gradient(150deg, rgb(45, 47, 60) 0%, rgb(40, 42, 55) 100%)",
        "text": "rgb(220, 220, 240)"
      }
    }
  },
  "tabs": {
    "bar": {
      "background": "linear-gradient(180deg, rgb(35, 37, 50) 0%, rgb(30, 32, 45) 100%)"
    },
    "tab": {
      "background": "linear-gradient(180deg, rgb(40, 42, 55) 0%, rgb(35, 37, 50) 100%)",
      "text": "rgb(180, 180, 200)",
      "hover": {
        "background": "linear-gradient(135deg, rgba(108, 99, 255, 0.85) 0%, rgba(138, 85, 255, 0.85) 100%)",
        "text": "rgb(255, 255, 255)"
      },
      "active": {
        "background": "linear-gradient(135deg, rgba(108, 99, 255, 0.85) 0%, rgba(138, 85, 255, 0.85) 100%)",
        "text": "rgb(255, 255, 255)"
      }
    }
  },
  "table": {
    "background": "linear-gradient(150deg, rgb(35, 37, 50) 0%, rgb(30, 32, 45) 100%)",
    "text": "rgb(220, 220, 240)",
    "header": {
      "background": "linear-gradient(135deg, rgba(108, 99, 255, 0.9) 0%, rgba(138, 85, 255, 0.9) 100%)",
      "text": "rgb(255, 255, 255)"
    },
    "row": {
      "background": "linear-gradient(150deg, rgb(40, 42, 55) 0%, rgb(35, 37, 50) 100%)",
      "text": "rgb(220, 220, 240)",
      "alt": {
        "background": "linear-gradient(150deg, rgb(35, 37, 50) 0%, rgb(30, 32, 45) 100%)",
        "text": "rgb(220, 220, 240)"
      }
    }
  },
  "input": {
    "background": "linear-gradient(150deg, rgb(45, 47, 60) 0%, rgb(40, 42, 55) 100%)",
    "text": "rgb(220, 220, 240)",
    "border": {
      "default": "rgba(70, 70, 85, 0.8)",
      "hover": "rgba(138, 85, 255, 0.6)",
      "focus": "rgba(108, 99, 255, 0.8)"
    },
    "placeholder": "rgb(150, 150, 170)"
  },
  "checkbox": {
    "background": "linear-gradient(150deg, rgb(45, 47, 60) 0%, rgb(40, 42, 55) 100%)",
    "checked": {
      "background": "linear-gradient(135deg, rgb(108, 99, 255) 0%, rgb(138, 85, 255) 100%)",
      "check": "rgb(255, 255, 255)"
    }
  },
  "label": {
    "text": "rgb(220, 220, 240)"
  },
  "progressBar": {
    "background": "linear-gradient(90deg, rgb(40, 42, 55) 0%, rgb(35, 37, 50) 100%)",
    "fill": "linear-gradient(90deg, rgb(80, 210, 194) 0%, rgb(108, 99, 255) 100%)",
    "text": "rgb(220, 220, 240)"
  },
  "overlay": {
    "background": "linear-gradient(150deg, rgba(25, 27, 40, 0.85) 0%, rgba(20, 22, 35, 0.85) 100%)"
  },
  "scrollbars": {
    "track": "rgba(35, 37, 50, 0.4)",
    "thumb": "rgba(108, 99, 255, 0.6)",
    "thumbHover": "rgba(138, 85, 255, 0.8)",
    "width": "6px",
    "borderRadius": "3px"
  },
  "dimensions": {
    "button": {
      "height": "50px",
      "width": "100%",
      "iconOnlyWidth": "50px",
      "borderRadius": "12px"
    },
    "panel": {
      "borderRadius": "16px"
    },
    "tabs": {
      "borderRadius": "8px 8px 0 0"
    },
    "settings": {
      "width": "100vw",
      "height": "100vh"
    }
  }
}
',
    '0',
    '2025-02-27 23:40:21',
    '2025-03-22 11:30:48',
    'Dark',
    '1'
  ),
  (
    '33',
    'Cave Dweller',
    '{
  "links": {
    "default": "rgb(46, 204, 113)",
    "hover": "rgb(76, 234, 143)",
    "active": "rgb(106, 255, 173)",
    "disabled": "rgba(60, 60, 60, 0.6)"
  },
  "buttons": {
    "primary": {
      "default": {
        "background": "linear-gradient(135deg, rgb(39, 174, 96) 0%, rgb(46, 204, 113) 100%)",
        "text": "rgb(255, 255, 255)"
      },
      "hover": {
        "background": "linear-gradient(135deg, rgb(59, 194, 116) 0%, rgb(66, 224, 133) 100%)",
        "text": "rgb(255, 255, 255)"
      },
      "active": {
        "background": "linear-gradient(135deg, rgb(79, 214, 136) 0%, rgb(86, 244, 153) 100%)",
        "text": "rgb(255, 255, 255)"
      },
      "disabled": {
        "background": "linear-gradient(135deg, rgba(39, 174, 96, 0.5) 0%, rgba(46, 204, 113, 0.5) 100%)",
        "text": "rgba(255, 255, 255, 0.7)"
      }
    },
    "secondary": {
      "default": {
        "background": "linear-gradient(135deg, rgba(25, 25, 30, 0.9) 0%, rgba(15, 15, 20, 0.9) 100%)",
        "text": "rgb(220, 220, 240)"
      },
      "hover": {
        "background": "linear-gradient(135deg, rgba(35, 35, 40, 1) 0%, rgba(25, 25, 30, 1) 100%)",
        "text": "rgb(230, 230, 250)"
      },
      "active": {
        "background": "linear-gradient(135deg, rgba(45, 45, 50, 1) 0%, rgba(35, 35, 40, 1) 100%)",
        "text": "rgb(240, 240, 255)"
      },
      "disabled": {
        "background": "linear-gradient(135deg, rgba(20, 20, 25, 0.5) 0%, rgba(10, 10, 15, 0.5) 100%)",
        "text": "rgba(200, 200, 220, 0.5)"
      }
    }
  },
  "uploader": {
    "background": "linear-gradient(160deg, rgba(10, 10, 12, 0.95) 0%, rgba(0, 0, 0, 0.95) 100%)",
    "text": "rgb(220, 220, 240)",
    "header": {
      "background": "linear-gradient(160deg, rgba(18, 18, 20, 0.8) 0%, rgba(12, 12, 15, 0.8) 100%)",
      "text": "rgb(230, 230, 250)"
    },
    "item": {
      "background": "linear-gradient(160deg, rgba(22, 22, 25, 0.9) 0%, rgba(15, 15, 18, 0.9) 100%)",
      "text": "rgb(220, 220, 240)"
    }
  },
  "panel": {
    "background": "linear-gradient(150deg, rgb(8, 8, 10) 0%, rgb(0, 0, 0) 100%)",
    "text": "rgb(220, 220, 240)",
    "item": {
      "background": "linear-gradient(150deg, rgb(15, 15, 18) 0%, rgb(10, 10, 12) 100%)",
      "text": "rgb(220, 220, 240)",
      "hover": {
        "background": "linear-gradient(150deg, rgb(22, 22, 25) 0%, rgb(18, 18, 20) 100%)",
        "text": "rgb(230, 230, 250)"
      },
      "action": {
        "hover": {
          "backgroundFrom": "rgba(22, 22, 25, 0.42)",
          "backgroundTo": "rgba(18, 18, 20, 0.8)"
        }
      }
    },
    "header": {
      "background": "linear-gradient(135deg, rgba(39, 174, 96, 0.85) 0%, rgba(46, 204, 113, 0.85) 100%)",
      "text": "rgb(255, 255, 255)"
    },
    "textAlt": "rgb(170, 170, 190)",
    "subheader": {
      "background": "linear-gradient(to bottom, rgb(18, 18, 20) 0%, rgb(12, 12, 15) 100%)",
      "text": "rgb(220, 220, 240)"
    },
    "nav": {
      "item": {
        "background": "linear-gradient(150deg, rgb(15, 15, 18) 0%, rgb(10, 10, 12) 100%)",
        "text": "rgb(220, 220, 240)",
        "hover": {
          "background": "linear-gradient(135deg, rgba(39, 174, 96, 0.85) 0%, rgba(46, 204, 113, 0.85) 100%)",
          "text": "rgb(255, 255, 255)"
        }
      }
    },
    "section": {
      "background": "linear-gradient(150deg, rgb(15, 15, 18) 0%, rgb(10, 10, 12) 100%)",
      "text": "rgb(220, 220, 240)",
      "alt": {
        "background": "linear-gradient(150deg, rgb(18, 18, 20) 0%, rgb(12, 12, 15) 100%)",
        "text": "rgb(220, 220, 240)"
      }
    }
  },
  "tabs": {
    "bar": {
      "background": "linear-gradient(180deg, rgb(10, 10, 12) 0%, rgb(5, 5, 8) 100%)"
    },
    "tab": {
      "background": "linear-gradient(180deg, rgb(15, 15, 18) 0%, rgb(10, 10, 12) 100%)",
      "text": "rgb(180, 180, 200)",
      "hover": {
        "background": "linear-gradient(135deg, rgba(39, 174, 96, 0.85) 0%, rgba(46, 204, 113, 0.85) 100%)",
        "text": "rgb(255, 255, 255)"
      },
      "active": {
        "background": "linear-gradient(135deg, rgba(39, 174, 96, 0.85) 0%, rgba(46, 204, 113, 0.85) 100%)",
        "text": "rgb(255, 255, 255)"
      }
    }
  },
  "table": {
    "background": "linear-gradient(150deg, rgb(8, 8, 10) 0%, rgb(0, 0, 0) 100%)",
    "text": "rgb(220, 220, 240)",
    "header": {
      "background": "linear-gradient(135deg, rgba(39, 174, 96, 0.9) 0%, rgba(46, 204, 113, 0.9) 100%)",
      "text": "rgb(255, 255, 255)"
    },
    "row": {
      "background": "linear-gradient(150deg, rgb(15, 15, 18) 0%, rgb(10, 10, 12) 100%)",
      "text": "rgb(220, 220, 240)",
      "alt": {
        "background": "linear-gradient(150deg, rgb(8, 8, 10) 0%, rgb(0, 0, 0) 100%)",
        "text": "rgb(220, 220, 240)"
      }
    }
  },
  "input": {
    "background": "linear-gradient(150deg, rgb(18, 18, 20) 0%, rgb(12, 12, 15) 100%)",
    "text": "rgb(220, 220, 240)",
    "border": {
      "default": "rgba(40, 40, 50, 0.8)",
      "hover": "rgba(46, 204, 113, 0.6)",
      "focus": "rgba(39, 174, 96, 0.8)"
    },
    "placeholder": "rgb(140, 140, 160)"
  },
  "checkbox": {
    "background": "linear-gradient(150deg, rgb(18, 18, 20) 0%, rgb(12, 12, 15) 100%)",
    "checked": {
      "background": "linear-gradient(135deg, rgb(39, 174, 96) 0%, rgb(46, 204, 113) 100%)",
      "check": "rgb(255, 255, 255)"
    }
  },
  "label": {
    "text": "rgb(220, 220, 240)"
  },
  "progressBar": {
    "background": "linear-gradient(90deg, rgb(15, 15, 18) 0%, rgb(10, 10, 12) 100%)",
    "fill": "linear-gradient(90deg, rgb(46, 204, 113) 0%, rgb(39, 174, 96) 100%)",
    "text": "rgb(220, 220, 240)"
  },
  "overlay": {
    "background": "linear-gradient(150deg, rgba(5, 5, 8, 0.85) 0%, rgba(0, 0, 0, 0.85) 100%)"
  },
  "scrollbars": {
    "track": "rgba(10, 10, 12, 0.4)",
    "thumb": "rgba(39, 174, 96, 0.6)",
    "thumbHover": "rgba(46, 204, 113, 0.8)",
    "width": "6px",
    "borderRadius": "3px"
  },
  "dimensions": {
    "button": {
      "height": "50px",
      "width": "100%",
      "iconOnlyWidth": "50px",
      "borderRadius": "12px"
    },
    "panel": {
      "borderRadius": "16px"
    },
    "tabs": {
      "borderRadius": "8px 8px 0 0"
    },
    "settings": {
      "width": "100vw",
      "height": "100vh"
    }
  }
}
',
    '0',
    NULL,
    '2025-02-28 21:16:29',
    'Dark',
    '1'
  ) ON CONFLICT(id) DO
UPDATE
SET
  theme = excluded.theme,
  updated_at = CURRENT_TIMESTAMP;