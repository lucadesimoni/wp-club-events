/**
 * Club Events Gutenberg Blocks
 * Lightweight server-side-rendered blocks — no build step required.
 */
(function (blocks, element, blockEditor, components, i18n, data, htmlEntities) {
  var el       = element.createElement;
  var __       = i18n.__;
  var InspectorControls  = blockEditor.InspectorControls;
  var useBlockProps      = blockEditor.useBlockProps;
  var PanelColorSettings = blockEditor.PanelColorSettings;
  var PanelBody    = components.PanelBody;
  /*
   * Opt into the control styles that became the default in WordPress 7.0
   * (no bottom margin, 40px inputs). Older versions ignore the props;
   * without them WP 6.7-6.9 log deprecation warnings.
   */
  function modern(Control, props) {
    return function (p) { return el(Control, Object.assign({}, props, p)); };
  }
  var sized        = { __nextHasNoMarginBottom: true, __next40pxDefaultSize: true };
  var TextControl  = modern(components.TextControl, sized);
  var RangeControl = modern(components.RangeControl, sized);
  var SelectControl= modern(components.SelectControl, sized);
  var ToggleControl= modern(components.ToggleControl, { __nextHasNoMarginBottom: true });
  var Disabled     = components.Disabled;
  var useSelect    = data.useSelect;
  var decode       = htmlEntities && htmlEntities.decodeEntities
    ? htmlEntities.decodeEntities
    : function (s) { return s; };
  var ServerSideRender = window.wp && window.wp.serverSideRender
    ? window.wp.serverSideRender.default || window.wp.serverSideRender
    : null;

  /*
   * Category, alignment, spacing and colour supports come from the server
   * registration (CE_Shortcodes::block_args), so they are not repeated here.
   * Every block also takes an accent colour, picked from the theme palette.
   */
  function withStyle(attributes) {
    return Object.assign({ accentColor: { type: 'string', default: '' } }, attributes);
  }

  /*
   * A term dropdown fed by the REST API. Falls back to a slug text field
   * while the terms load (or when the REST request is blocked), and keeps a
   * slug saved earlier selectable even when that term no longer exists.
   */
  function TermSelect(p) {
    var terms = useSelect(function (select) {
      return select('core').getEntityRecords('taxonomy', p.taxonomy, { per_page: 100, hide_empty: false, _fields: 'id,name,slug' });
    }, [p.taxonomy]);

    if (!terms) {
      return el(TextControl, { label: p.label, help: p.help, value: p.value, onChange: p.onChange });
    }
    var options = [{ label: __('All', 'club-events'), value: '' }].concat(terms.map(function (t) {
      return { label: decode(t.name), value: t.slug };
    }));
    if (p.value && !terms.some(function (t) { return t.slug === p.value; })) {
      options.push({ label: p.value, value: p.value });
    }
    return el(SelectControl, { label: p.label, help: p.help, value: p.value, options: options, onChange: p.onChange });
  }

  /* Category + event type dropdowns — shared by every query-driven block. */
  function taxonomyControls(props) {
    var attrs = props.attributes;
    return [
      el(TermSelect, {
        key: 'category',
        taxonomy: 'event_category',
        label: __('Category', 'club-events'),
        help:  __('Filter by event category. Leave on "All" for every category.', 'club-events'),
        value: attrs.category,
        onChange: function (v) { props.setAttributes({ category: v }); },
      }),
      el(TermSelect, {
        key: 'event_type',
        taxonomy: 'event_type',
        label: __('Event type', 'club-events'),
        help:  __('Filter by event type. Leave on "All" for every type.', 'club-events'),
        value: attrs.event_type,
        onChange: function (v) { props.setAttributes({ event_type: v }); },
      }),
    ];
  }

  /* Inspector with the block's own panels plus the shared accent colour. */
  function Inspector(p) {
    var props  = p.props;
    var accent = PanelColorSettings
      ? el(PanelColorSettings, {
          title: __('Accent colour', 'club-events'),
          initialOpen: false,
          colorSettings: [{
            label: __('Accent', 'club-events'),
            value: props.attributes.accentColor,
            onChange: function (v) { props.setAttributes({ accentColor: v || '' }); },
          }],
        })
      : null;
    return el(InspectorControls, null, p.children, accent);
  }

  /* Live server-rendered preview, non-interactive inside the editor. */
  function preview(name, attrs, fallback) {
    if (!ServerSideRender) {
      return fallback;
    }
    return el(Disabled, null, el(ServerSideRender, {
      block: name,
      attributes: attrs,
      // Colours, spacing and classes are applied by the editor's own block
      // wrapper; sending them as well would apply them twice.
      skipBlockSupportAttributes: true,
    }));
  }

  /* edit() for blocks without settings of their own. */
  function simpleEdit(name, icon, label) {
    return function (props) {
      return el('div', useBlockProps(),
        el(Inspector, { props: props }),
        preview(name, props.attributes, placeholder(icon, label))
      );
    };
  }

  function filterControls(props) {
    var attrs = props.attributes;
    return taxonomyControls(props).concat([
      el(SelectControl, {
        key: 'filter_by',
        label: __('Filter bar shows', 'club-events'),
        value: attrs.filter_by,
        options: [
          { label: __('Categories', 'club-events'), value: 'category' },
          { label: __('Event Types', 'club-events'), value: 'event_type' },
        ],
        onChange: function (v) { props.setAttributes({ filter_by: v }); },
      }),
    ]);
  }

  /* Toggle bound to a boolean attribute. */
  function toggle(props, key, label) {
    return el(ToggleControl, {
      label: label,
      checked: !!props.attributes[key],
      onChange: function (v) { props.setAttributes(defineAttr(key, v)); },
    });
  }

  function defineAttr(key, value) {
    var o = {};
    o[key] = value;
    return o;
  }

  function placeholder(icon, label) {
    return el('div', { className: 'ce-block-placeholder' },
      el('span', { className: 'dashicons dashicons-' + icon }),
      el('p', null, label)
    );
  }

  /* ── Timeline Block ──────────────────────────────────────────────── */
  blocks.registerBlockType('club-events/timeline', {
    title:       __('Events Timeline', 'club-events'),
    description: __('Show upcoming club events in a vertical timeline.', 'club-events'),
    icon:        'list-view',
    apiVersion:  3,
    attributes: withStyle({
      category:    { type: 'string',  default: '' },
      event_type:  { type: 'string',  default: '' },
      filter_by:   { type: 'string',  default: 'category' },
      limit:       { type: 'number',  default: 20 },
      show_past:   { type: 'boolean', default: false },
      show_filter: { type: 'boolean', default: true },
      layout:      { type: 'string',  default: 'default' },
    }),
    edit: function (props) {
      var attrs = props.attributes;
      return el('div', useBlockProps(),
        el(Inspector, { props: props },
          el(PanelBody, { title: __('Query', 'club-events'), initialOpen: true },
            filterControls(props),
            el(RangeControl, {
              label: __('Max events', 'club-events'),
              value: attrs.limit, min: 1, max: 100,
              onChange: function (v) { props.setAttributes({ limit: v }); },
            }),
            toggle(props, 'show_past', __('Show past events', 'club-events'))
          ),
          el(PanelBody, { title: __('Display', 'club-events'), initialOpen: false },
            el(SelectControl, {
              label: __('Layout', 'club-events'),
              value: attrs.layout,
              options: [
                { label: __('Default (left rail)', 'club-events'), value: 'default' },
                { label: __('Centred (alternating)', 'club-events'), value: 'center' },
              ],
              onChange: function (v) { props.setAttributes({ layout: v }); },
            }),
            toggle(props, 'show_filter', __('Show filter bar', 'club-events'))
          )
        ),
        preview('club-events/timeline', attrs, placeholder('list-view', __('Events Timeline — preview in frontend.', 'club-events')))
      );
    },
    save: function () { return null; },
  });

  /* ── Overview Block ──────────────────────────────────────────────── */
  blocks.registerBlockType('club-events/overview', {
    title:       __('Events Calendar', 'club-events'),
    description: __('Show a monthly calendar grid of club events.', 'club-events'),
    icon:        'calendar-alt',
    apiVersion:  3,
    attributes: withStyle({
      category:    { type: 'string',  default: '' },
      event_type:  { type: 'string',  default: '' },
      filter_by:   { type: 'string',  default: 'category' },
      show_filter: { type: 'boolean', default: true },
    }),
    edit: function (props) {
      var attrs = props.attributes;
      return el('div', useBlockProps(),
        el(Inspector, { props: props },
          el(PanelBody, { title: __('Query', 'club-events'), initialOpen: true },
            filterControls(props)
          ),
          el(PanelBody, { title: __('Display', 'club-events'), initialOpen: false },
            el(ToggleControl, {
              label: __('Show filter bar', 'club-events'),
              checked: attrs.show_filter,
              onChange: function (v) { props.setAttributes({ show_filter: v }); },
            })
          )
        ),
        preview('club-events/overview', attrs, placeholder('calendar-alt', __('Events Calendar — preview in frontend.', 'club-events')))
      );
    },
    save: function () { return null; },
  });

  /* ── Cards Block ─────────────────────────────────────────────────── */
  blocks.registerBlockType('club-events/cards', {
    title:       __('Events Cards', 'club-events'),
    description: __('Show club events in a responsive card grid.', 'club-events'),
    icon:        'grid-view',
    apiVersion:  3,
    attributes: withStyle({
      category:    { type: 'string',  default: '' },
      event_type:  { type: 'string',  default: '' },
      filter_by:   { type: 'string',  default: 'category' },
      limit:       { type: 'number',  default: 6 },
      columns:     { type: 'number',  default: 3 },
      show_past:   { type: 'boolean', default: false },
      show_filter: { type: 'boolean', default: true },
      show_image:  { type: 'boolean', default: true },
    }),
    edit: function (props) {
      var attrs = props.attributes;
      return el('div', useBlockProps(),
        el(Inspector, { props: props },
          el(PanelBody, { title: __('Query', 'club-events'), initialOpen: true },
            filterControls(props),
            el(RangeControl, { label: __('Max events', 'club-events'), value: attrs.limit, min: 1, max: 50, onChange: function(v){ props.setAttributes({limit:v}); } }),
            el(ToggleControl, { label: __('Show past events', 'club-events'), checked: attrs.show_past, onChange: function(v){ props.setAttributes({show_past:v}); } })
          ),
          el(PanelBody, { title: __('Display', 'club-events'), initialOpen: false },
            el(RangeControl, { label: __('Columns', 'club-events'), value: attrs.columns, min: 1, max: 4, onChange: function(v){ props.setAttributes({columns:v}); } }),
            el(ToggleControl, { label: __('Show image', 'club-events'), checked: attrs.show_image, onChange: function(v){ props.setAttributes({show_image:v}); } }),
            el(ToggleControl, { label: __('Show filter bar', 'club-events'), checked: attrs.show_filter, onChange: function(v){ props.setAttributes({show_filter:v}); } })
          )
        ),
        preview('club-events/cards', attrs, placeholder('grid-view', __('Events Cards', 'club-events')))
      );
    },
    save: function () { return null; },
  });

  /* ── List Block ──────────────────────────────────────────────────── */
  blocks.registerBlockType('club-events/list', {
    title:       __('Events List', 'club-events'),
    description: __('Show upcoming events in a compact list.', 'club-events'),
    icon:        'editor-ul',
    apiVersion:  3,
    attributes: withStyle({
      category:    { type: 'string',  default: '' },
      event_type:  { type: 'string',  default: '' },
      limit:       { type: 'number',  default: 5 },
      show_past:   { type: 'boolean', default: false },
    }),
    edit: function (props) {
      var attrs = props.attributes;
      return el('div', useBlockProps(),
        el(Inspector, { props: props },
          el(PanelBody, { title: __('Query', 'club-events'), initialOpen: true },
            taxonomyControls(props),
            el(RangeControl, {
              label: __('Max events', 'club-events'),
              value: attrs.limit, min: 1, max: 50,
              onChange: function (v) { props.setAttributes({ limit: v }); },
            }),
            el(ToggleControl, {
              label: __('Show past events', 'club-events'),
              checked: attrs.show_past,
              onChange: function (v) { props.setAttributes({ show_past: v }); },
            })
          )
        ),
        preview('club-events/list', attrs, placeholder('editor-ul', __('Events List — preview in frontend.', 'club-events')))
      );
    },
    save: function () { return null; },
  });

  /* ── Yearly Agenda Block ──────────────────────────────────────────── */
  blocks.registerBlockType('club-events/yearly', {
    title:       __('Yearly Agenda', 'club-events'),
    description: __('Show a full-year event agenda grouped by month.', 'club-events'),
    icon:        'calendar',
    apiVersion:  3,
    attributes: withStyle({
      category:   { type: 'string', default: '' },
      event_type: { type: 'string', default: '' },
      year:       { type: 'number', default: 0 },
    }),
    edit: function (props) {
      var attrs = props.attributes;
      return el('div', useBlockProps(),
        el(Inspector, { props: props },
          el(PanelBody, { title: __('Query', 'club-events'), initialOpen: true },
            taxonomyControls(props),
            el(RangeControl, {
              label: __('Year (0 = current)', 'club-events'),
              value: attrs.year, min: 0, max: 2099,
              onChange: function (v) { props.setAttributes({ year: v }); },
            })
          )
        ),
        preview('club-events/yearly', attrs, placeholder('calendar', __('Yearly Agenda — preview in frontend.', 'club-events')))
      );
    },
    save: function () { return null; },
  });

  /* ── Events Hub Block ────────────────────────────────────────────── */
  blocks.registerBlockType('club-events/hub', {
    title:       __('Events Hub', 'club-events'),
    description: __('Search, view switcher (tiles / list / timeline / calendar) and ICS subscribe — the complete events page.', 'club-events'),
    icon:        'calendar-alt',
    apiVersion:  3,
    keywords:    [ __('events', 'club-events'), __('hub', 'club-events'), __('calendar', 'club-events') ],
    attributes: withStyle({
      category:       { type: 'string',  default: '' },
      event_type:     { type: 'string',  default: '' },
      filter_by:      { type: 'string',  default: 'category' },
      limit:          { type: 'number',  default: 60 },
      views:          { type: 'string',  default: 'tiles,list,timeline,calendar' },
      default:        { type: 'string',  default: '' },
      columns:        { type: 'number',  default: 3 },
      show_search:    { type: 'boolean', default: true },
      show_filter:    { type: 'boolean', default: true },
      show_subscribe: { type: 'boolean', default: true },
      show_past:      { type: 'boolean', default: false },
    }),
    edit: function (props) {
      var attrs = props.attributes;
      return el('div', useBlockProps(),
        el(Inspector, { props: props },
          el(PanelBody, { title: __('Query', 'club-events'), initialOpen: true },
            filterControls(props),
            el(RangeControl, {
              label: __('Max events', 'club-events'),
              value: attrs.limit, min: 1, max: 200,
              onChange: function (v) { props.setAttributes({ limit: v }); },
            }),
            toggle(props, 'show_past', __('Show past events', 'club-events'))
          ),
          el(PanelBody, { title: __('Views', 'club-events'), initialOpen: false },
            el(TextControl, {
              label: __('Enabled views', 'club-events'),
              help:  __('Comma-separated, in order: tiles, list, timeline, calendar.', 'club-events'),
              value: attrs.views,
              onChange: function (v) { props.setAttributes({ views: v }); },
            }),
            el(SelectControl, {
              label: __('Default view', 'club-events'),
              help:  __('Falls back to the first enabled view.', 'club-events'),
              value: attrs.default,
              options: [
                { label: __('First enabled view', 'club-events'), value: '' },
                { label: __('Tiles', 'club-events'),    value: 'tiles' },
                { label: __('List', 'club-events'),     value: 'list' },
                { label: __('Timeline', 'club-events'), value: 'timeline' },
                { label: __('Calendar', 'club-events'), value: 'calendar' },
              ],
              onChange: function (v) { props.setAttributes({ default: v }); },
            })
          ),
          el(PanelBody, { title: __('Display', 'club-events'), initialOpen: false },
            el(RangeControl, {
              label: __('Tile columns', 'club-events'),
              value: attrs.columns, min: 1, max: 4,
              onChange: function (v) { props.setAttributes({ columns: v }); },
            }),
            toggle(props, 'show_search',    __('Show search box', 'club-events')),
            toggle(props, 'show_filter',    __('Show filter bar', 'club-events')),
            toggle(props, 'show_subscribe', __('Show Subscribe (ICS) button', 'club-events'))
          )
        ),
        preview('club-events/hub', attrs, placeholder('calendar-alt', __('Events Hub — preview in frontend.', 'club-events')))
      );
    },
    save: function () { return null; },
  });

  /* ── Event Tiles Block ───────────────────────────────────────────── */
  blocks.registerBlockType('club-events/tiles', {
    title:       __('Event Tiles', 'club-events'),
    description: __('Blog-card style previews of the next events.', 'club-events'),
    icon:        'grid-view',
    apiVersion:  3,
    keywords:    [ __('events', 'club-events'), __('tiles', 'club-events'), __('cards', 'club-events') ],
    attributes: withStyle({
      category:      { type: 'string',  default: '' },
      event_type:    { type: 'string',  default: '' },
      limit:         { type: 'number',  default: 6 },
      columns:       { type: 'number',  default: 3 },
      show_image:    { type: 'boolean', default: true },
      show_excerpt:  { type: 'boolean', default: true },
      show_location: { type: 'boolean', default: false },
      show_time:     { type: 'boolean', default: false },
      show_types:    { type: 'boolean', default: false },
      show_share:    { type: 'boolean', default: false },
      show_ics:      { type: 'boolean', default: false },
      cta:           { type: 'string',  default: 'Weiterlesen' },
    }),
    edit: function (props) {
      var attrs = props.attributes;
      return el('div', useBlockProps(),
        el(Inspector, { props: props },
          el(PanelBody, { title: __('Query', 'club-events'), initialOpen: true },
            taxonomyControls(props),
            el(RangeControl, {
              label: __('Max events', 'club-events'),
              value: attrs.limit, min: 1, max: 24,
              onChange: function (v) { props.setAttributes({ limit: v }); },
            })
          ),
          el(PanelBody, { title: __('Display', 'club-events'), initialOpen: false },
            el(RangeControl, {
              label: __('Columns', 'club-events'),
              value: attrs.columns, min: 1, max: 4,
              onChange: function (v) { props.setAttributes({ columns: v }); },
            }),
            toggle(props, 'show_image',    __('Show image', 'club-events')),
            toggle(props, 'show_excerpt',  __('Show excerpt', 'club-events')),
            toggle(props, 'show_location', __('Show location', 'club-events')),
            toggle(props, 'show_time',     __('Show time', 'club-events')),
            toggle(props, 'show_types',    __('Show event type badges', 'club-events')),
            toggle(props, 'show_share',    __('Show share button', 'club-events')),
            toggle(props, 'show_ics',      __('Show "Add to calendar" button', 'club-events')),
            el(TextControl, {
              label: __('Call to action label', 'club-events'),
              value: attrs.cta,
              onChange: function (v) { props.setAttributes({ cta: v }); },
            })
          )
        ),
        preview('club-events/tiles', attrs, placeholder('grid-view', __('Event Tiles — preview in frontend.', 'club-events')))
      );
    },
    save: function () { return null; },
  });

  /* ── Subscribe Block ─────────────────────────────────────────────── */
  blocks.registerBlockType('club-events/subscribe', {
    title:       __('Events Subscribe Form', 'club-events'),
    description: __('Email subscription form for event notifications.', 'club-events'),
    icon:        'email-alt',
    apiVersion:  3,
    attributes: withStyle({}),
    edit: simpleEdit('club-events/subscribe', 'email-alt', __('Events Subscribe Form', 'club-events')),
    save: function () { return null; },
  });

  /* ── Share Actions Block ────────────────────────────────────────── */
  blocks.registerBlockType('club-events/share', {
    title:       __('Event Share Actions', 'club-events'),
    description: __('Share and "Add to calendar" buttons for the current event.', 'club-events'),
    icon:        'share',
    apiVersion:  3,
    keywords:    [ __('share', 'club-events'), __('ics', 'club-events'), __('calendar', 'club-events') ],
    attributes: withStyle({
      url:    { type: 'string', default: '' },
      title:  { type: 'string', default: '' },
      ics:    { type: 'string', default: '' },
      labels: { type: 'string', default: 'true' },
    }),
    edit: function (props) {
      var attrs = props.attributes;
      return el('div', useBlockProps(),
        el(Inspector, { props: props },
          el(PanelBody, { title: __('Share target', 'club-events'), initialOpen: true },
            el(TextControl, {
              label: __('URL', 'club-events'),
              help:  __('Leave empty to share the current event or page.', 'club-events'),
              value: attrs.url,
              onChange: function (v) { props.setAttributes({ url: v }); },
            }),
            el(TextControl, {
              label: __('Title', 'club-events'),
              help:  __('Leave empty to use the current event or site title.', 'club-events'),
              value: attrs.title,
              onChange: function (v) { props.setAttributes({ title: v }); },
            }),
            el(TextControl, {
              label: __('ICS URL', 'club-events'),
              help:  __('Leave empty to use the current event\'s .ics file.', 'club-events'),
              value: attrs.ics,
              onChange: function (v) { props.setAttributes({ ics: v }); },
            }),
            el(ToggleControl, {
              label: __('Show button labels', 'club-events'),
              checked: 'false' !== attrs.labels,
              onChange: function (v) { props.setAttributes({ labels: v ? 'true' : 'false' }); },
            })
          )
        ),
        preview('club-events/share', attrs, placeholder('share', __('Event Share Actions', 'club-events')))
      );
    },
    save: function () { return null; },
  });

  /* ── Submit Event Block ─────────────────────────────────────────── */
  blocks.registerBlockType('club-events/submit', {
    title:       __('Event Submit Form', 'club-events'),
    description: __('Frontend form for logged-in users to submit events.', 'club-events'),
    icon:        'edit-page',
    apiVersion:  3,
    attributes: withStyle({}),
    edit: simpleEdit('club-events/submit', 'edit-page', __('Event Submit Form — requires login on frontend.', 'club-events')),
    save: function () { return null; },
  });

  /* ── My Events Block ────────────────────────────────────────────── */
  blocks.registerBlockType('club-events/my-events', {
    title:       __('My Events', 'club-events'),
    description: __('Show logged-in user\'s submitted events with status.', 'club-events'),
    icon:        'id-alt',
    apiVersion:  3,
    attributes: withStyle({}),
    edit: simpleEdit('club-events/my-events', 'id-alt', __('My Events — requires login on frontend.', 'club-events')),
    save: function () { return null; },
  });

}(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n, window.wp.data, window.wp.htmlEntities));
