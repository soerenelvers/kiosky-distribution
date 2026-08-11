CREATE TABLE tx_kiosky_state (
  uid int(11) unsigned NOT NULL auto_increment,
  state_json mediumtext,
  updated_at int(11) unsigned NOT NULL default '0',
  PRIMARY KEY (uid)
);

CREATE TABLE tx_kiosky_update_state (
  uid int(11) unsigned NOT NULL,
  status_json mediumtext,
  updated_at int(11) unsigned NOT NULL default '0',
  PRIMARY KEY (uid)
);

CREATE TABLE tx_kiosky_migration_map (
  uid int(11) unsigned NOT NULL auto_increment,
  source_type varchar(64) NOT NULL default '',
  source_id varchar(255) NOT NULL default '',
  target_table varchar(128) NOT NULL default '',
  target_uid int(11) unsigned NOT NULL default '0',
  migrated_at int(11) unsigned NOT NULL default '0',
  source_hash varchar(64) NOT NULL default '',
  PRIMARY KEY (uid),
  UNIQUE KEY source (source_type, source_id),
  KEY target (target_table, target_uid)
);

CREATE TABLE be_users (
  tx_kiosky_role varchar(32) NOT NULL default ''
);

CREATE TABLE tt_content (
  tx_kiosky_calendar_title varchar(120) NOT NULL default '',
  tx_kiosky_upcoming_title varchar(120) NOT NULL default '',
  tx_kiosky_upcoming_count int(11) unsigned NOT NULL default '10',
  tx_kiosky_show_search smallint(5) unsigned NOT NULL default '1',
  tx_kiosky_show_upcoming smallint(5) unsigned NOT NULL default '1'
);

CREATE TABLE tx_kiosky_domain_model_location (
  uid int(11) unsigned NOT NULL auto_increment,
  pid int(11) unsigned NOT NULL default '0',
  tstamp int(11) unsigned NOT NULL default '0',
  crdate int(11) unsigned NOT NULL default '0',
  cruser_id int(11) unsigned NOT NULL default '0',
  deleted smallint(5) unsigned NOT NULL default '0',
  hidden smallint(5) unsigned NOT NULL default '0',
  starttime int(11) unsigned NOT NULL default '0',
  endtime int(11) unsigned NOT NULL default '0',
  sorting int(11) unsigned NOT NULL default '0',
  name varchar(255) NOT NULL default '',
  organisation varchar(255) NOT NULL default '',
  street varchar(255) NOT NULL default '',
  house_number varchar(64) NOT NULL default '',
  postal_code varchar(32) NOT NULL default '',
  city varchar(255) NOT NULL default '',
  country varchar(2) NOT NULL default '',
  building varchar(255) NOT NULL default '',
  floor varchar(64) NOT NULL default '',
  room varchar(255) NOT NULL default '',
  latitude decimal(10,7) DEFAULT NULL,
  longitude decimal(10,7) DEFAULT NULL,
  timezone varchar(64) NOT NULL default 'Europe/Berlin',
  description text,
  PRIMARY KEY (uid),
  KEY parent (pid)
);

CREATE TABLE tx_kiosky_domain_model_displaygroup (
  uid int(11) unsigned NOT NULL auto_increment,
  pid int(11) unsigned NOT NULL default '0',
  tstamp int(11) unsigned NOT NULL default '0',
  crdate int(11) unsigned NOT NULL default '0',
  cruser_id int(11) unsigned NOT NULL default '0',
  deleted smallint(5) unsigned NOT NULL default '0',
  hidden smallint(5) unsigned NOT NULL default '0',
  starttime int(11) unsigned NOT NULL default '0',
  endtime int(11) unsigned NOT NULL default '0',
  sorting int(11) unsigned NOT NULL default '0',
  name varchar(255) NOT NULL default '',
  public_uuid varchar(36) NOT NULL default '',
  parent int(11) unsigned NOT NULL default '0',
  description text,
  tags text,
  PRIMARY KEY (uid),
  KEY parent_pid (pid)
);

CREATE TABLE tx_kiosky_domain_model_display (
  uid int(11) unsigned NOT NULL auto_increment,
  pid int(11) unsigned NOT NULL default '0',
  tstamp int(11) unsigned NOT NULL default '0',
  crdate int(11) unsigned NOT NULL default '0',
  cruser_id int(11) unsigned NOT NULL default '0',
  deleted smallint(5) unsigned NOT NULL default '0',
  hidden smallint(5) unsigned NOT NULL default '0',
  starttime int(11) unsigned NOT NULL default '0',
  endtime int(11) unsigned NOT NULL default '0',
  sorting int(11) unsigned NOT NULL default '0',
  name varchar(255) NOT NULL default '',
  identifier varchar(128) NOT NULL default '',
  public_uuid varchar(36) NOT NULL default '',
  token_hash varchar(255) NOT NULL default '',
  display_group int(11) unsigned NOT NULL default '0',
  location int(11) unsigned NOT NULL default '0',
  room varchar(255) NOT NULL default '',
  address text,
  width int(11) unsigned NOT NULL default '1920',
  height int(11) unsigned NOT NULL default '1080',
  orientation varchar(16) NOT NULL default 'landscape',
  default_channel int(11) unsigned NOT NULL default '0',
  default_playlist int(11) unsigned NOT NULL default '0',
  default_background int(11) unsigned NOT NULL default '0',
  current_channel int(11) unsigned NOT NULL default '0',
  current_playlist int(11) unsigned NOT NULL default '0',
  last_heartbeat int(11) unsigned NOT NULL default '0',
  player_version varchar(64) NOT NULL default '',
  status varchar(32) NOT NULL default 'unknown',
  description text,
  tags text,
  PRIMARY KEY (uid),
  KEY parent (pid),
  UNIQUE KEY public_uuid (public_uuid),
  UNIQUE KEY identifier (identifier),
  KEY heartbeat (last_heartbeat)
);

CREATE TABLE tx_kiosky_domain_model_channel (
  uid int(11) unsigned NOT NULL auto_increment,
  pid int(11) unsigned NOT NULL default '0',
  tstamp int(11) unsigned NOT NULL default '0',
  crdate int(11) unsigned NOT NULL default '0',
  cruser_id int(11) unsigned NOT NULL default '0',
  deleted smallint(5) unsigned NOT NULL default '0',
  hidden smallint(5) unsigned NOT NULL default '0',
  starttime int(11) unsigned NOT NULL default '0',
  endtime int(11) unsigned NOT NULL default '0',
  sorting int(11) unsigned NOT NULL default '0',
  name varchar(255) NOT NULL default '',
  public_uuid varchar(36) NOT NULL default '',
  description text,
  priority int(11) NOT NULL default '0',
  playlist int(11) unsigned NOT NULL default '0',
  PRIMARY KEY (uid),
  KEY parent (pid),
  UNIQUE KEY public_uuid (public_uuid)
);

CREATE TABLE tx_kiosky_domain_model_playlist (
  uid int(11) unsigned NOT NULL auto_increment,
  pid int(11) unsigned NOT NULL default '0',
  tstamp int(11) unsigned NOT NULL default '0',
  crdate int(11) unsigned NOT NULL default '0',
  cruser_id int(11) unsigned NOT NULL default '0',
  deleted smallint(5) unsigned NOT NULL default '0',
  hidden smallint(5) unsigned NOT NULL default '0',
  starttime int(11) unsigned NOT NULL default '0',
  endtime int(11) unsigned NOT NULL default '0',
  sorting int(11) unsigned NOT NULL default '0',
  name varchar(255) NOT NULL default '',
  public_uuid varchar(36) NOT NULL default '',
  description text,
  orientation varchar(16) NOT NULL default 'auto',
  slides int(11) unsigned NOT NULL default '0',
  PRIMARY KEY (uid),
  KEY parent (pid),
  UNIQUE KEY public_uuid (public_uuid)
);

CREATE TABLE tx_kiosky_domain_model_slide (
  uid int(11) unsigned NOT NULL auto_increment,
  pid int(11) unsigned NOT NULL default '0',
  tstamp int(11) unsigned NOT NULL default '0',
  crdate int(11) unsigned NOT NULL default '0',
  cruser_id int(11) unsigned NOT NULL default '0',
  deleted smallint(5) unsigned NOT NULL default '0',
  hidden smallint(5) unsigned NOT NULL default '0',
  starttime int(11) unsigned NOT NULL default '0',
  endtime int(11) unsigned NOT NULL default '0',
  sorting int(11) unsigned NOT NULL default '0',
  name varchar(255) NOT NULL default '',
  public_uuid varchar(36) NOT NULL default '',
  slide_type varchar(32) NOT NULL default 'text',
  template int(11) unsigned NOT NULL default '0',
  content_json mediumtext,
  media int(11) unsigned NOT NULL default '0',
  duration int(11) unsigned NOT NULL default '10',
  weekdays varchar(32) NOT NULL default '',
  priority int(11) NOT NULL default '0',
  transition_name varchar(32) NOT NULL default 'none',
  PRIMARY KEY (uid),
  KEY parent (pid),
  UNIQUE KEY public_uuid (public_uuid)
);

CREATE TABLE tx_kiosky_playlist_slide_mm (
  uid_local int(11) unsigned NOT NULL default '0',
  uid_foreign int(11) unsigned NOT NULL default '0',
  sorting int(11) unsigned NOT NULL default '0',
  duration int(11) unsigned NOT NULL default '0',
  transition_name varchar(32) NOT NULL default '',
  KEY parent (uid_local),
  KEY child (uid_foreign)
);

CREATE TABLE tx_kiosky_domain_model_template (
  uid int(11) unsigned NOT NULL auto_increment,
  pid int(11) unsigned NOT NULL default '0',
  tstamp int(11) unsigned NOT NULL default '0',
  crdate int(11) unsigned NOT NULL default '0',
  cruser_id int(11) unsigned NOT NULL default '0',
  deleted smallint(5) unsigned NOT NULL default '0',
  hidden smallint(5) unsigned NOT NULL default '0',
  starttime int(11) unsigned NOT NULL default '0',
  endtime int(11) unsigned NOT NULL default '0',
  sorting int(11) unsigned NOT NULL default '0',
  name varchar(255) NOT NULL default '',
  public_uuid varchar(36) NOT NULL default '',
  description text,
  category varchar(128) NOT NULL default '',
  preview int(11) unsigned NOT NULL default '0',
  width int(11) unsigned NOT NULL default '1920',
  height int(11) unsigned NOT NULL default '1080',
  orientation varchar(16) NOT NULL default 'landscape',
  definition_json mediumtext,
  PRIMARY KEY (uid),
  KEY parent (pid),
  UNIQUE KEY public_uuid (public_uuid)
);

CREATE TABLE tx_kiosky_domain_model_event (
  uid int(11) unsigned NOT NULL auto_increment,
  pid int(11) unsigned NOT NULL default '0',
  tstamp int(11) unsigned NOT NULL default '0',
  crdate int(11) unsigned NOT NULL default '0',
  cruser_id int(11) unsigned NOT NULL default '0',
  deleted smallint(5) unsigned NOT NULL default '0',
  hidden smallint(5) unsigned NOT NULL default '0',
  starttime int(11) unsigned NOT NULL default '0',
  endtime int(11) unsigned NOT NULL default '0',
  sorting int(11) unsigned NOT NULL default '0',
  title varchar(255) NOT NULL default '',
  subtitle varchar(255) NOT NULL default '',
  description text,
  event_date int(11) unsigned NOT NULL default '0',
  event_start int(11) unsigned NOT NULL default '0',
  admission_start int(11) unsigned NOT NULL default '0',
  break_start int(11) unsigned NOT NULL default '0',
  event_end int(11) unsigned NOT NULL default '0',
  setup_start int(11) unsigned NOT NULL default '0',
  teardown_end int(11) unsigned NOT NULL default '0',
  location int(11) unsigned NOT NULL default '0',
  hall varchar(255) NOT NULL default '',
  room_usage varchar(255) NOT NULL default '',
  organizer varchar(255) NOT NULL default '',
  image int(11) unsigned NOT NULL default '0',
  remaining_tickets varchar(64) NOT NULL default '',
  box_office smallint(5) unsigned NOT NULL default '0',
  box_office_open int(11) unsigned NOT NULL default '0',
  notes text,
  channel int(11) unsigned NOT NULL default '0',
  playlist int(11) unsigned NOT NULL default '0',
  status varchar(32) NOT NULL default 'draft',
  external_id varchar(255) NOT NULL default '',
  external_data_source int(11) unsigned NOT NULL default '0',
  last_sync int(11) unsigned NOT NULL default '0',
  PRIMARY KEY (uid),
  KEY parent (pid),
  KEY external (external_data_source, external_id)
);

CREATE TABLE tx_kiosky_domain_model_preset (
  uid int(11) unsigned NOT NULL auto_increment,
  pid int(11) unsigned NOT NULL default '0',
  tstamp int(11) unsigned NOT NULL default '0',
  crdate int(11) unsigned NOT NULL default '0',
  cruser_id int(11) unsigned NOT NULL default '0',
  deleted smallint(5) unsigned NOT NULL default '0',
  hidden smallint(5) unsigned NOT NULL default '0',
  starttime int(11) unsigned NOT NULL default '0',
  endtime int(11) unsigned NOT NULL default '0',
  sorting int(11) unsigned NOT NULL default '0',
  name varchar(255) NOT NULL default '',
  public_uuid varchar(36) NOT NULL default '',
  description text,
  priority int(11) NOT NULL default '0',
  target_displays text,
  target_groups text,
  channel int(11) unsigned NOT NULL default '0',
  playlist int(11) unsigned NOT NULL default '0',
  slide int(11) unsigned NOT NULL default '0',
  automatic_return smallint(5) unsigned NOT NULL default '0',
  return_channel int(11) unsigned NOT NULL default '0',
  confirmation_required smallint(5) unsigned NOT NULL default '0',
  emergency smallint(5) unsigned NOT NULL default '0',
  active smallint(5) unsigned NOT NULL default '0',
  activated_at int(11) unsigned NOT NULL default '0',
  PRIMARY KEY (uid),
  KEY parent (pid),
  UNIQUE KEY public_uuid (public_uuid)
);

CREATE TABLE tx_kiosky_domain_model_schedule (
  uid int(11) unsigned NOT NULL auto_increment,
  pid int(11) unsigned NOT NULL default '0',
  tstamp int(11) unsigned NOT NULL default '0',
  crdate int(11) unsigned NOT NULL default '0',
  cruser_id int(11) unsigned NOT NULL default '0',
  deleted smallint(5) unsigned NOT NULL default '0',
  hidden smallint(5) unsigned NOT NULL default '0',
  starttime int(11) unsigned NOT NULL default '0',
  endtime int(11) unsigned NOT NULL default '0',
  sorting int(11) unsigned NOT NULL default '0',
  name varchar(255) NOT NULL default '',
  target_type varchar(32) NOT NULL default 'display',
  target_uid int(11) unsigned NOT NULL default '0',
  content_type varchar(32) NOT NULL default 'channel',
  content_uid int(11) unsigned NOT NULL default '0',
  recurrence varchar(32) NOT NULL default 'once',
  weekdays varchar(32) NOT NULL default '',
  priority int(11) NOT NULL default '0',
  PRIMARY KEY (uid),
  KEY parent (pid),
  KEY window (starttime, endtime)
);

CREATE TABLE tx_kiosky_domain_model_emergencymessage (
  uid int(11) unsigned NOT NULL auto_increment,
  pid int(11) unsigned NOT NULL default '0',
  tstamp int(11) unsigned NOT NULL default '0',
  crdate int(11) unsigned NOT NULL default '0',
  cruser_id int(11) unsigned NOT NULL default '0',
  deleted smallint(5) unsigned NOT NULL default '0',
  hidden smallint(5) unsigned NOT NULL default '0',
  starttime int(11) unsigned NOT NULL default '0',
  endtime int(11) unsigned NOT NULL default '0',
  sorting int(11) unsigned NOT NULL default '0',
  title varchar(255) NOT NULL default '',
  message text,
  severity varchar(32) NOT NULL default 'warning',
  graphic int(11) unsigned NOT NULL default '0',
  target_displays text,
  target_groups text,
  active smallint(5) unsigned NOT NULL default '0',
  activated_by int(11) unsigned NOT NULL default '0',
  PRIMARY KEY (uid),
  KEY parent (pid)
);

CREATE TABLE tx_kiosky_domain_model_displaycommand (
  uid int(11) unsigned NOT NULL auto_increment,
  pid int(11) unsigned NOT NULL default '0',
  tstamp int(11) unsigned NOT NULL default '0',
  crdate int(11) unsigned NOT NULL default '0',
  display int(11) unsigned NOT NULL default '0',
  command_uuid varchar(36) NOT NULL default '',
  command_type varchar(64) NOT NULL default '',
  payload_json mediumtext,
  status varchar(32) NOT NULL default 'pending',
  acknowledged_at int(11) unsigned NOT NULL default '0',
  PRIMARY KEY (uid),
  UNIQUE KEY command_uuid (command_uuid),
  KEY pending (display, status)
);

CREATE TABLE tx_kiosky_domain_model_displayheartbeat (
  uid int(11) unsigned NOT NULL auto_increment,
  pid int(11) unsigned NOT NULL default '0',
  tstamp int(11) unsigned NOT NULL default '0',
  crdate int(11) unsigned NOT NULL default '0',
  display int(11) unsigned NOT NULL default '0',
  received_at int(11) unsigned NOT NULL default '0',
  player_version varchar(64) NOT NULL default '',
  ip_hash varchar(64) NOT NULL default '',
  state_json mediumtext,
  PRIMARY KEY (uid),
  KEY display_time (display, received_at)
);

CREATE TABLE tx_kiosky_domain_model_auditlog (
  uid int(11) unsigned NOT NULL auto_increment,
  pid int(11) unsigned NOT NULL default '0',
  tstamp int(11) unsigned NOT NULL default '0',
  backend_user int(11) unsigned NOT NULL default '0',
  action varchar(128) NOT NULL default '',
  entity_type varchar(128) NOT NULL default '',
  entity_uid int(11) unsigned NOT NULL default '0',
  request_id varchar(64) NOT NULL default '',
  metadata_json mediumtext,
  created_at int(11) unsigned NOT NULL default '0',
  PRIMARY KEY (uid),
  KEY entity (entity_type, entity_uid),
  KEY created (created_at)
);

CREATE TABLE tx_kiosky_domain_model_externaldatasource (
  uid int(11) unsigned NOT NULL auto_increment,
  pid int(11) unsigned NOT NULL default '0',
  tstamp int(11) unsigned NOT NULL default '0',
  crdate int(11) unsigned NOT NULL default '0',
  cruser_id int(11) unsigned NOT NULL default '0',
  deleted smallint(5) unsigned NOT NULL default '0',
  hidden smallint(5) unsigned NOT NULL default '0',
  starttime int(11) unsigned NOT NULL default '0',
  endtime int(11) unsigned NOT NULL default '0',
  sorting int(11) unsigned NOT NULL default '0',
  name varchar(255) NOT NULL default '',
  provider varchar(64) NOT NULL default '',
  base_url text,
  encrypted_credentials mediumtext,
  configuration_json mediumtext,
  sync_enabled smallint(5) unsigned NOT NULL default '0',
  last_sync int(11) unsigned NOT NULL default '0',
  last_error text,
  PRIMARY KEY (uid),
  KEY parent (pid)
);
