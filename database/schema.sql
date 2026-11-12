-- =====================================================================
--  VIKAO MARKETPLACE — PRODUCTION SCHEMA (MySQL 8.0+ / MariaDB 10.6+)
--  vikao.io  ·  Ecommerce app (standalone DB, integrates with PMS via
--  pms_events outbox + pms_* reference columns)
-- ---------------------------------------------------------------------
--  Design principles
--   * utf8mb4 everywhere, InnoDB, BIGINT UNSIGNED surrogate keys
--   * public-facing UUIDs (never expose auto-increment ids in URLs/APIs)
--   * soft deletes (deleted_at) on user-generated content
--   * typed detail tables per listing family (fast, indexable filters)
--     instead of a slow generic EAV
--   * multi-country location tree (Kenya now, East & Central Africa next)
--   * OAuth-ready identity (password optional, provider table)
--   * every money column = DECIMAL(14,2) + ISO currency code
--   * outbox tables for email + PMS so integrations are async & retryable
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS vikao CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE vikao;

-- ---------------------------------------------------------------------
-- 1. GEOGRAPHY
-- ---------------------------------------------------------------------
CREATE TABLE countries (
  id            SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  iso2          CHAR(2)      NOT NULL UNIQUE,
  iso3          CHAR(3)      NOT NULL UNIQUE,
  name          VARCHAR(80)  NOT NULL,
  phone_code    VARCHAR(6)   NOT NULL,
  currency_code CHAR(3)      NOT NULL,
  region        ENUM('east_africa','central_africa','horn_of_africa','other') NOT NULL DEFAULT 'east_africa',
  -- what each admin level is called locally (Kenya: County > Sub-county > Area)
  level1_label  VARCHAR(40)  NOT NULL DEFAULT 'Region',
  level2_label  VARCHAR(40)  NOT NULL DEFAULT 'District',
  level3_label  VARCHAR(40)  NOT NULL DEFAULT 'Area',
  is_active     TINYINT(1)   NOT NULL DEFAULT 0,
  sort_order    SMALLINT     NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adjacency-list tree: level 1 = county/region, 2 = sub-county/district, 3 = area/estate/ward
CREATE TABLE locations (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  country_id  SMALLINT UNSIGNED NOT NULL,
  parent_id   INT UNSIGNED NULL,
  level       TINYINT UNSIGNED NOT NULL,
  name        VARCHAR(120) NOT NULL,
  slug        VARCHAR(160) NOT NULL,
  full_name   VARCHAR(400) NOT NULL,           -- "Kilimani, Dagoretti North, Nairobi"
  code        VARCHAR(20)  NULL,               -- official code (e.g. county 047)
  latitude    DECIMAL(10,7) NULL,
  longitude   DECIMAL(10,7) NULL,
  is_popular  TINYINT(1)   NOT NULL DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  UNIQUE KEY uq_loc_slug (country_id, slug),
  KEY idx_loc_parent (parent_id),
  KEY idx_loc_level (country_id, level),
  FULLTEXT KEY ft_loc_name (name, full_name),
  CONSTRAINT fk_loc_country FOREIGN KEY (country_id) REFERENCES countries(id),
  CONSTRAINT fk_loc_parent  FOREIGN KEY (parent_id)  REFERENCES locations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. IDENTITY & ACCOUNTS
-- ---------------------------------------------------------------------
CREATE TABLE membership_tiers (
  id            TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code          VARCHAR(20) NOT NULL UNIQUE,   -- bronze | silver | gold
  name          VARCHAR(40) NOT NULL,
  rank_order    TINYINT NOT NULL,
  color_hex     CHAR(7) NOT NULL,
  icon          VARCHAR(40) NOT NULL,
  max_active_ads INT UNSIGNED NULL,            -- NULL = unlimited
  monthly_price DECIMAL(14,2) NOT NULL DEFAULT 0,
  currency_code CHAR(3) NOT NULL DEFAULT 'KES',
  perks         JSON NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid               CHAR(36)     NOT NULL UNIQUE,
  account_type       ENUM('normal','official') NOT NULL DEFAULT 'normal',
  role               ENUM('user','staff','admin') NOT NULL DEFAULT 'user', -- staff/admin = PMS reviewers
  first_name         VARCHAR(80)  NOT NULL,
  last_name          VARCHAR(80)  NULL,
  display_name       VARCHAR(120) NOT NULL,
  email              VARCHAR(190) NOT NULL UNIQUE,
  phone              VARCHAR(20)  NULL,          -- E.164 e.g. +254712345678
  phone_country_id   SMALLINT UNSIGNED NULL,
  password_hash      VARCHAR(255) NULL,          -- NULL for OAuth-only accounts
  avatar_url         VARCHAR(500) NULL,
  bio                VARCHAR(500) NULL,
  location_id        INT UNSIGNED NULL,
  membership_tier_id TINYINT UNSIGNED NULL,
  membership_expires_at DATETIME NULL,
  email_verified_at  DATETIME NULL,
  phone_verified_at  DATETIME NULL,
  status             ENUM('active','suspended','banned','pending') NOT NULL DEFAULT 'active',
  locale             VARCHAR(10)  NOT NULL DEFAULT 'en-KE',
  theme              ENUM('system','light','dark') NOT NULL DEFAULT 'system',
  last_login_at      DATETIME NULL,
  last_seen_at       DATETIME NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at         DATETIME NULL,
  UNIQUE KEY uq_users_phone (phone),
  KEY idx_users_type (account_type),
  CONSTRAINT fk_users_tier    FOREIGN KEY (membership_tier_id) REFERENCES membership_tiers(id),
  CONSTRAINT fk_users_loc     FOREIGN KEY (location_id) REFERENCES locations(id),
  CONSTRAINT fk_users_country FOREIGN KEY (phone_country_id) REFERENCES countries(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OAuth (Google / Apple / Facebook) — one user can link many providers
CREATE TABLE user_oauth_accounts (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id           BIGINT UNSIGNED NOT NULL,
  provider          ENUM('google','apple','facebook') NOT NULL,
  provider_user_id  VARCHAR(191) NOT NULL,
  provider_email    VARCHAR(190) NULL,
  access_token_enc  TEXT NULL,       -- encrypted at rest by app
  refresh_token_enc TEXT NULL,
  token_expires_at  DATETIME NULL,
  raw_profile       JSON NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_oauth_provider (provider, provider_user_id),
  UNIQUE KEY uq_oauth_user_provider (user_id, provider),
  CONSTRAINT fk_oauth_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- remember-me / API / mobile (Flutter) tokens — store only SHA-256 hashes
CREATE TABLE user_tokens (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     BIGINT UNSIGNED NOT NULL,
  type        ENUM('remember','api','password_reset','email_verify','phone_otp') NOT NULL,
  token_hash  CHAR(64) NOT NULL UNIQUE,
  device_name VARCHAR(120) NULL,
  ip_address  VARCHAR(45) NULL,
  expires_at  DATETIME NOT NULL,
  used_at     DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tok_user (user_id, type),
  CONSTRAINT fk_tok_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE badges (
  id          SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code        VARCHAR(30) NOT NULL UNIQUE,     -- verified | popular | official | top_seller ...
  name        VARCHAR(60) NOT NULL,
  description VARCHAR(255) NULL,
  icon        VARCHAR(40) NOT NULL,
  color_hex   CHAR(7) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_badges (
  user_id    BIGINT UNSIGNED NOT NULL,
  badge_id   SMALLINT UNSIGNED NOT NULL,
  awarded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NULL,
  PRIMARY KEY (user_id, badge_id),
  CONSTRAINT fk_ub_user  FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ub_badge FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. TAXONOMY
-- ---------------------------------------------------------------------
-- Tree: root families (residential, commercial, land, short-stay, services)
--  -> leaf categories (residential-for-sale, ..., services/plumbing ...)
CREATE TABLE categories (
  id            SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id     SMALLINT UNSIGNED NULL,
  family        ENUM('residential','commercial','land','short_stay','service') NOT NULL,
  deal_type     ENUM('sale','rent','short_stay','service') NULL,  -- NULL on roots / service groups
  name          VARCHAR(80)  NOT NULL,
  slug          VARCHAR(100) NOT NULL UNIQUE,
  icon          VARCHAR(40)  NOT NULL DEFAULT 'fa-folder',
  description   VARCHAR(255) NULL,
  is_postable   TINYINT(1) NOT NULL DEFAULT 1,   -- can an ad be posted directly here
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  sort_order    SMALLINT NOT NULL DEFAULT 0,
  KEY idx_cat_parent (parent_id),
  KEY idx_cat_family (family, deal_type),
  CONSTRAINT fk_cat_parent FOREIGN KEY (parent_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE property_types (
  id         SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  family     ENUM('residential','commercial','land') NOT NULL,  -- short_stay uses residential
  name       VARCHAR(80) NOT NULL,
  slug       VARCHAR(100) NOT NULL,
  icon       VARCHAR(40) NOT NULL DEFAULT 'fa-building',
  sort_order SMALLINT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_ptype (family, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Amenities / features. One row may apply to several families.
CREATE TABLE features (
  id           SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(80) NOT NULL,
  slug         VARCHAR(100) NOT NULL UNIQUE,
  icon         VARCHAR(40) NOT NULL DEFAULT 'fa-check',
  feature_group VARCHAR(40) NOT NULL DEFAULT 'General', -- Security, Utilities, Comfort, Outdoor, Safety, Tech...
  families     SET('residential','commercial','land','short_stay') NOT NULL,
  sort_order   SMALLINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. LISTINGS (ADS)
-- ---------------------------------------------------------------------
CREATE TABLE listings (
  id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid                  CHAR(36) NOT NULL UNIQUE,
  ref_code              VARCHAR(16) NOT NULL UNIQUE,     -- human ref e.g. VK-7F3K9Q
  user_id               BIGINT UNSIGNED NOT NULL,
  category_id           SMALLINT UNSIGNED NOT NULL,
  family                ENUM('residential','commercial','land','short_stay','service') NOT NULL, -- denormalised for speed
  deal_type             ENUM('sale','rent','short_stay','service') NOT NULL,
  is_official           TINYINT(1) NOT NULL DEFAULT 0,   -- posted by official Vikao store (via PMS)
  title                 VARCHAR(140) NOT NULL,
  slug                  VARCHAR(170) NOT NULL,
  description_html      MEDIUMTEXT NOT NULL,             -- sanitised rich text
  description_text      TEXT NOT NULL,                   -- plain text for search/snippets
  -- location
  country_id            SMALLINT UNSIGNED NOT NULL,
  location_id           INT UNSIGNED NOT NULL,           -- deepest level chosen
  region_id             INT UNSIGNED NULL,               -- level-1 ancestor (county) — denormalised for filters
  address               VARCHAR(255) NULL,
  estate                VARCHAR(120) NULL,
  latitude              DECIMAL(10,7) NULL,
  longitude             DECIMAL(10,7) NULL,
  -- pricing
  price                 DECIMAL(14,2) NULL,              -- NULL = "Contact for price" (services)
  currency_code         CHAR(3) NOT NULL DEFAULT 'KES',
  price_unit            ENUM('entire','sqft','sqm','acre','hectare','plot','hour','day','job','visit','sqm_service','unit') NOT NULL DEFAULT 'entire',
  billing_period        ENUM('once','hour','night','day','week','month','quarter','half_year','year') NOT NULL DEFAULT 'once',
  is_negotiable         TINYINT(1) NOT NULL DEFAULT 0,
  reservation_fee       DECIMAL(14,2) NULL,              -- official listings only
  has_service_charge    TINYINT(1) NOT NULL DEFAULT 0,
  service_charge_amount DECIMAL(14,2) NULL,
  service_charge_period ENUM('month','quarter','year') NULL,
  service_charge_notes  VARCHAR(500) NULL,
  listed_by             ENUM('owner','agent','developer','company','individual') NOT NULL DEFAULT 'owner',
  -- media
  youtube_url           VARCHAR(255) NULL,               -- 360° walkthrough
  youtube_video_id      VARCHAR(20) NULL,
  cover_media_id        BIGINT UNSIGNED NULL,
  -- lifecycle
  status                ENUM('draft','pending','active','rejected','closed','expired','sold') NOT NULL DEFAULT 'pending',
  close_reason          VARCHAR(120) NULL,
  is_premium            TINYINT(1) NOT NULL DEFAULT 0,   -- boosted
  premium_until         DATETIME NULL,
  popularity_score      DECIMAL(10,4) NOT NULL DEFAULT 0, -- recomputed by job
  views_count           INT UNSIGNED NOT NULL DEFAULT 0,
  favourites_count      INT UNSIGNED NOT NULL DEFAULT 0,
  chats_count           INT UNSIGNED NOT NULL DEFAULT 0,
  phone_reveals_count   INT UNSIGNED NOT NULL DEFAULT 0,
  -- PMS integration
  pms_property_id       VARCHAR(64) NULL,                -- property/unit in PMS this ad was pushed from
  pms_unit_id           VARCHAR(64) NULL,
  submitted_at          DATETIME NULL,
  published_at          DATETIME NULL,
  expires_at            DATETIME NULL,
  closed_at             DATETIME NULL,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at            DATETIME NULL,
  KEY idx_l_browse   (status, family, deal_type, published_at),
  KEY idx_l_cat      (status, category_id, published_at),
  KEY idx_l_loc      (status, region_id, location_id),
  KEY idx_l_price    (status, price),
  KEY idx_l_user     (user_id, status),
  KEY idx_l_official (is_official, status),
  KEY idx_l_premium  (is_premium, status, popularity_score),
  KEY idx_l_pms      (pms_unit_id),
  FULLTEXT KEY ft_listing (title, description_text, estate, address),
  CONSTRAINT fk_l_user     FOREIGN KEY (user_id)     REFERENCES users(id),
  CONSTRAINT fk_l_cat      FOREIGN KEY (category_id) REFERENCES categories(id),
  CONSTRAINT fk_l_country  FOREIGN KEY (country_id)  REFERENCES countries(id),
  CONSTRAINT fk_l_loc      FOREIGN KEY (location_id) REFERENCES locations(id),
  CONSTRAINT fk_l_region   FOREIGN KEY (region_id)   REFERENCES locations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE listing_media (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id  BIGINT UNSIGNED NOT NULL,
  media_type  ENUM('image','floor_plan','document') NOT NULL DEFAULT 'image',
  storage     ENUM('local','s3','cdn') NOT NULL DEFAULT 'local',
  path        VARCHAR(500) NOT NULL,
  thumb_path  VARCHAR(500) NULL,
  mime_type   VARCHAR(60) NOT NULL,
  size_bytes  INT UNSIGNED NOT NULL,
  width       SMALLINT UNSIGNED NULL,
  height      SMALLINT UNSIGNED NULL,
  sort_order  SMALLINT NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_media_listing (listing_id, sort_order),
  CONSTRAINT fk_media_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Residential + Short Stay (BnB)
CREATE TABLE listing_residential_details (
  listing_id        BIGINT UNSIGNED PRIMARY KEY,
  property_type_id  SMALLINT UNSIGNED NOT NULL,
  bedrooms          TINYINT UNSIGNED NULL,        -- 0 = studio/bedsitter
  bathrooms         TINYINT UNSIGNED NULL,
  toilets           TINYINT UNSIGNED NULL,
  size_value        DECIMAL(12,2) NULL,
  size_unit         ENUM('sqft','sqm') NOT NULL DEFAULT 'sqm',
  size_sqm          DECIMAL(12,2) NULL,           -- normalised for filtering
  self_contained    TINYINT(1) NULL,
  `condition`       ENUM('new','used','renovated','off_plan') NULL,
  furnishing        ENUM('furnished','semi_furnished','unfurnished') NULL,
  floor_number      SMALLINT NULL,
  total_floors      SMALLINT NULL,
  parking_spaces    TINYINT UNSIGNED NULL,
  year_built        SMALLINT UNSIGNED NULL,
  pets_allowed      TINYINT(1) NULL,
  -- short stay only
  max_guests        TINYINT UNSIGNED NULL,
  beds              TINYINT UNSIGNED NULL,
  min_nights        SMALLINT UNSIGNED NULL,
  check_in_time     TIME NULL,
  check_out_time    TIME NULL,
  cleaning_fee      DECIMAL(14,2) NULL,
  security_deposit  DECIMAL(14,2) NULL,
  KEY idx_rd_filter (property_type_id, bedrooms, bathrooms),
  KEY idx_rd_size (size_sqm),
  CONSTRAINT fk_rd_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  CONSTRAINT fk_rd_ptype   FOREIGN KEY (property_type_id) REFERENCES property_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE listing_commercial_details (
  listing_id        BIGINT UNSIGNED PRIMARY KEY,
  property_type_id  SMALLINT UNSIGNED NOT NULL,
  size_value        DECIMAL(12,2) NULL,
  size_unit         ENUM('sqft','sqm') NOT NULL DEFAULT 'sqm',
  size_sqm          DECIMAL(12,2) NULL,
  parking_spaces    SMALLINT UNSIGNED NULL,
  `condition`       ENUM('new','used','renovated','off_plan') NULL,
  furnishing        ENUM('furnished','semi_furnished','unfurnished') NULL,
  floor_number      SMALLINT NULL,
  total_floors      SMALLINT NULL,
  capacity_people   INT UNSIGNED NULL,           -- halls, event grounds, conference
  washrooms         TINYINT UNSIGNED NULL,
  power_phase       ENUM('single','three') NULL,
  zoning            ENUM('commercial','industrial','mixed_use','special') NULL,
  KEY idx_cd_filter (property_type_id, size_sqm),
  CONSTRAINT fk_cd_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  CONSTRAINT fk_cd_ptype   FOREIGN KEY (property_type_id) REFERENCES property_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE listing_land_details (
  listing_id         BIGINT UNSIGNED PRIMARY KEY,
  property_type_id   SMALLINT UNSIGNED NOT NULL,  -- land use: residential plot, agricultural, ...
  size_value         DECIMAL(12,4) NOT NULL,
  size_unit          ENUM('acre','hectare','sqm','sqft','plot_50x100','plot_40x80','plot_100x100') NOT NULL DEFAULT 'acre',
  size_acres         DECIMAL(12,4) NULL,          -- normalised for filtering
  tenure             ENUM('freehold','leasehold','community','not_sure') NULL,
  lease_years_remaining SMALLINT UNSIGNED NULL,
  title_status       ENUM('title_deed','allotment_letter','share_certificate','lease_certificate','pending_subdivision','processing','none') NULL,
  zoning             ENUM('residential','commercial','agricultural','industrial','mixed_use','institutional') NULL,
  topography         ENUM('flat','gentle_slope','steep','valley','hilltop','waterfront') NULL,
  soil_type          ENUM('red_soil','black_cotton','loam','sandy','rocky','murram','volcanic') NULL,
  road_access        ENUM('tarmac','murram','gravel','footpath','none') NULL,
  distance_to_tarmac_km DECIMAL(6,2) NULL,
  is_corner_plot     TINYINT(1) NULL,
  is_subdivided      TINYINT(1) NULL,
  is_fenced          TINYINT(1) NULL,
  is_serviced        TINYINT(1) NULL,           -- water/power/sewer on site
  payment_plan       TINYINT(1) NOT NULL DEFAULT 0,
  KEY idx_ld_filter (property_type_id, size_acres),
  CONSTRAINT fk_ld_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  CONSTRAINT fk_ld_ptype   FOREIGN KEY (property_type_id) REFERENCES property_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE listing_service_details (
  listing_id          BIGINT UNSIGNED PRIMARY KEY,
  provider_type       ENUM('individual','company','freelancer','agency') NOT NULL DEFAULT 'individual',
  business_name       VARCHAR(140) NULL,
  pricing_model       ENUM('fixed','hourly','daily','per_sqm','per_unit','per_visit','quote') NOT NULL DEFAULT 'quote',
  years_experience    TINYINT UNSIGNED NULL,
  team_size           ENUM('solo','2_5','6_20','21_50','50_plus') NULL,
  availability        ENUM('weekdays','weekends','everyday','on_call') NULL,
  emergency_service   TINYINT(1) NOT NULL DEFAULT 0,
  provides_materials  TINYINT(1) NOT NULL DEFAULT 0,
  free_site_visit     TINYINT(1) NOT NULL DEFAULT 0,
  is_insured          TINYINT(1) NOT NULL DEFAULT 0,
  warranty_months     SMALLINT UNSIGNED NULL,
  license_body        VARCHAR(80) NULL,          -- NCA, EPRA, BORAQS, EBK, LSK, ISK ...
  license_number      VARCHAR(80) NULL,
  response_time       ENUM('within_1h','same_day','within_24h','within_3d') NULL,
  languages           VARCHAR(120) NULL,
  CONSTRAINT fk_sd_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Areas a service provider covers (many locations)
CREATE TABLE listing_service_areas (
  listing_id  BIGINT UNSIGNED NOT NULL,
  location_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (listing_id, location_id),
  KEY idx_lsa_loc (location_id),
  CONSTRAINT fk_lsa_listing FOREIGN KEY (listing_id)  REFERENCES listings(id)  ON DELETE CASCADE,
  CONSTRAINT fk_lsa_loc     FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE listing_features (
  listing_id BIGINT UNSIGNED NOT NULL,
  feature_id SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (listing_id, feature_id),
  KEY idx_lf_feature (feature_id, listing_id),
  CONSTRAINT fk_lf_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  CONSTRAINT fk_lf_feature FOREIGN KEY (feature_id) REFERENCES features(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Purchase / lease packages on official listings (shown on order page)
CREATE TABLE listing_packages (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id      BIGINT UNSIGNED NOT NULL,
  name            VARCHAR(100) NOT NULL,          -- "Cash purchase", "12-month instalments", "1-year lease"
  description     VARCHAR(500) NULL,
  package_type    ENUM('outright','installment','lease','short_stay','custom') NOT NULL DEFAULT 'outright',
  total_price     DECIMAL(14,2) NOT NULL,
  deposit_amount  DECIMAL(14,2) NULL,
  installments    SMALLINT UNSIGNED NULL,
  installment_amount DECIMAL(14,2) NULL,
  duration_months SMALLINT UNSIGNED NULL,
  is_recommended  TINYINT(1) NOT NULL DEFAULT 0,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  sort_order      SMALLINT NOT NULL DEFAULT 0,
  KEY idx_pkg_listing (listing_id, is_active),
  CONSTRAINT fk_pkg_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every submission (new or edit) and every moderator decision — audit trail
CREATE TABLE listing_reviews (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id       BIGINT UNSIGNED NOT NULL,
  submission_type  ENUM('new','edit','resubmit') NOT NULL,
  snapshot         JSON NULL,                     -- listing state at submission
  decision         ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reason           VARCHAR(500) NULL,
  reviewer_id      BIGINT UNSIGNED NULL,
  reviewer_source  ENUM('pms','system') NOT NULL DEFAULT 'pms',
  submitted_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decided_at       DATETIME NULL,
  KEY idx_rev_queue (decision, submitted_at),
  KEY idx_rev_listing (listing_id),
  CONSTRAINT fk_rev_listing  FOREIGN KEY (listing_id)  REFERENCES listings(id) ON DELETE CASCADE,
  CONSTRAINT fk_rev_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE listing_reports (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id  BIGINT UNSIGNED NOT NULL,
  reporter_id BIGINT UNSIGNED NULL,
  reason      ENUM('scam','wrong_category','sold','duplicate','offensive','wrong_price','other') NOT NULL,
  details     VARCHAR(1000) NULL,
  status      ENUM('open','reviewed','dismissed') NOT NULL DEFAULT 'open',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rep_status (status),
  CONSTRAINT fk_rep_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  CONSTRAINT fk_rep_user    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daily roll-up keeps the hot listings row small (no per-view rows)
CREATE TABLE listing_stats_daily (
  listing_id     BIGINT UNSIGNED NOT NULL,
  stat_date      DATE NOT NULL,
  views          INT UNSIGNED NOT NULL DEFAULT 0,
  phone_reveals  INT UNSIGNED NOT NULL DEFAULT 0,
  chats_started  INT UNSIGNED NOT NULL DEFAULT 0,
  favourites     INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (listing_id, stat_date),
  CONSTRAINT fk_lsd_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE favourites (
  user_id    BIGINT UNSIGNED NOT NULL,
  listing_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, listing_id),
  KEY idx_fav_listing (listing_id),
  CONSTRAINT fk_fav_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  CONSTRAINT fk_fav_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE saved_searches (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     BIGINT UNSIGNED NOT NULL,
  name        VARCHAR(120) NOT NULL,
  query       JSON NOT NULL,
  notify      TINYINT(1) NOT NULL DEFAULT 1,
  last_notified_at DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ss_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. CHAT  (one conversation per listing per buyer)
-- ---------------------------------------------------------------------
CREATE TABLE conversations (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid               CHAR(36) NOT NULL UNIQUE,
  listing_id         BIGINT UNSIGNED NOT NULL,
  buyer_id           BIGINT UNSIGNED NOT NULL,
  seller_id          BIGINT UNSIGNED NOT NULL,
  last_message_id    BIGINT UNSIGNED NULL,
  last_message_at    DATETIME NULL,
  buyer_unread       INT UNSIGNED NOT NULL DEFAULT 0,
  seller_unread      INT UNSIGNED NOT NULL DEFAULT 0,
  buyer_archived_at  DATETIME NULL,
  seller_archived_at DATETIME NULL,
  blocked_by         BIGINT UNSIGNED NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_conv (listing_id, buyer_id),
  KEY idx_conv_buyer  (buyer_id, last_message_at),
  KEY idx_conv_seller (seller_id, last_message_at),
  CONSTRAINT fk_conv_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  CONSTRAINT fk_conv_buyer   FOREIGN KEY (buyer_id)   REFERENCES users(id)    ON DELETE CASCADE,
  CONSTRAINT fk_conv_seller  FOREIGN KEY (seller_id)  REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id BIGINT UNSIGNED NOT NULL,
  sender_id       BIGINT UNSIGNED NOT NULL,
  body            VARCHAR(2000) NOT NULL,      -- text + links only (no file uploads in v1)
  message_type    ENUM('text','system','offer') NOT NULL DEFAULT 'text',
  read_at         DATETIME NULL,
  created_at      DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  deleted_at      DATETIME NULL,
  KEY idx_msg_conv (conversation_id, id),
  CONSTRAINT fk_msg_conv   FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_sender FOREIGN KEY (sender_id)       REFERENCES users(id)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. ORDERS & PAYMENTS (official listings)
-- ---------------------------------------------------------------------
CREATE TABLE orders (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid              CHAR(36) NOT NULL UNIQUE,
  order_number      VARCHAR(20) NOT NULL UNIQUE,     -- VKO-20261017-48213
  user_id           BIGINT UNSIGNED NOT NULL,
  listing_id        BIGINT UNSIGNED NOT NULL,
  package_id        BIGINT UNSIGNED NULL,
  -- contact captured on order page
  contact_name      VARCHAR(160) NOT NULL,
  contact_email     VARCHAR(190) NOT NULL,
  contact_phone     VARCHAR(20)  NOT NULL,
  id_number         VARCHAR(30)  NULL,
  preferred_contact ENUM('phone','email','whatsapp') NOT NULL DEFAULT 'phone',
  preferred_viewing_date DATE NULL,
  notes             VARCHAR(1000) NULL,
  -- money (snapshot at time of order)
  listing_snapshot  JSON NOT NULL,
  package_snapshot  JSON NULL,
  package_total     DECIMAL(14,2) NOT NULL DEFAULT 0,
  reservation_fee   DECIMAL(14,2) NOT NULL,
  amount_due        DECIMAL(14,2) NOT NULL,          -- what is paid now (reservation fee)
  amount_paid       DECIMAL(14,2) NOT NULL DEFAULT 0,
  currency_code     CHAR(3) NOT NULL DEFAULT 'KES',
  status            ENUM('pending_payment','paid','processing','completed','cancelled','refunded','expired') NOT NULL DEFAULT 'pending_payment',
  pms_sync_status   ENUM('pending','sent','acknowledged','failed') NOT NULL DEFAULT 'pending',
  pms_reference     VARCHAR(64) NULL,
  paid_at           DATETIME NULL,
  cancelled_at      DATETIME NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_ord_user (user_id, created_at),
  KEY idx_ord_listing (listing_id, status),
  KEY idx_ord_status (status),
  CONSTRAINT fk_ord_user    FOREIGN KEY (user_id)    REFERENCES users(id),
  CONSTRAINT fk_ord_listing FOREIGN KEY (listing_id) REFERENCES listings(id),
  CONSTRAINT fk_ord_package FOREIGN KEY (package_id) REFERENCES listing_packages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_status_history (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id    BIGINT UNSIGNED NOT NULL,
  from_status VARCHAR(30) NULL,
  to_status   VARCHAR(30) NOT NULL,
  note        VARCHAR(255) NULL,
  actor       ENUM('customer','system','pms','payment') NOT NULL DEFAULT 'system',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_osh_order (order_id),
  CONSTRAINT fk_osh_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_methods (
  id          TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code        VARCHAR(30) NOT NULL UNIQUE,     -- mpesa | card | airtel_money | bank_transfer
  name        VARCHAR(60) NOT NULL,
  icon        VARCHAR(60) NOT NULL,
  country_id  SMALLINT UNSIGNED NULL,          -- NULL = global
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  is_simulated TINYINT(1) NOT NULL DEFAULT 0,
  sort_order  SMALLINT NOT NULL DEFAULT 0,
  CONSTRAINT fk_pm_country FOREIGN KEY (country_id) REFERENCES countries(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid                  CHAR(36) NOT NULL UNIQUE,
  order_id              BIGINT UNSIGNED NOT NULL,
  payment_method_id     TINYINT UNSIGNED NOT NULL,
  amount                DECIMAL(14,2) NOT NULL,
  currency_code         CHAR(3) NOT NULL DEFAULT 'KES',
  payer_phone           VARCHAR(20) NULL,
  -- M-Pesa Daraja STK fields (ready for real integration)
  merchant_request_id   VARCHAR(64) NULL,
  checkout_request_id   VARCHAR(64) NULL UNIQUE,
  provider_receipt      VARCHAR(40) NULL UNIQUE,   -- e.g. M-Pesa receipt QJK3XXXXXX
  result_code           VARCHAR(10) NULL,
  result_desc           VARCHAR(255) NULL,
  status                ENUM('initiated','pending','success','failed','cancelled','timeout') NOT NULL DEFAULT 'initiated',
  is_simulated          TINYINT(1) NOT NULL DEFAULT 0,
  request_payload       JSON NULL,
  callback_payload      JSON NULL,
  initiated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at          DATETIME NULL,
  KEY idx_pay_order (order_id),
  KEY idx_pay_status (status),
  CONSTRAINT fk_pay_order  FOREIGN KEY (order_id) REFERENCES orders(id),
  CONSTRAINT fk_pay_method FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. NOTIFICATIONS, EMAIL, PMS OUTBOX
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     BIGINT UNSIGNED NOT NULL,
  type        VARCHAR(40) NOT NULL,     -- ad_approved, ad_rejected, new_message, order_paid, ...
  title       VARCHAR(160) NOT NULL,
  body        VARCHAR(500) NULL,
  icon        VARCHAR(40) NULL,
  link_url    VARCHAR(500) NULL,
  data        JSON NULL,
  read_at     DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notif_user (user_id, read_at, created_at),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification_preferences (
  user_id    BIGINT UNSIGNED NOT NULL,
  channel    ENUM('in_app','email','sms','push') NOT NULL,
  type       VARCHAR(40) NOT NULL,
  enabled    TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (user_id, channel, type),
  CONSTRAINT fk_np_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE push_devices (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     BIGINT UNSIGNED NOT NULL,
  platform    ENUM('ios','android','web') NOT NULL,
  token       VARCHAR(500) NOT NULL,
  last_used_at DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_push_token (token(255)),
  CONSTRAINT fk_push_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_outbox (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  to_email    VARCHAR(190) NOT NULL,
  to_name     VARCHAR(160) NULL,
  subject     VARCHAR(255) NOT NULL,
  body_html   MEDIUMTEXT NOT NULL,
  template    VARCHAR(60) NULL,
  status      ENUM('queued','sent','failed','simulated') NOT NULL DEFAULT 'queued',
  attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error  VARCHAR(500) NULL,
  related_type VARCHAR(40) NULL,
  related_id  BIGINT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at     DATETIME NULL,
  KEY idx_mail_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transactional outbox: everything the PMS must know about (orders, new ads to review ...)
CREATE TABLE pms_events (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_type   VARCHAR(60) NOT NULL,        -- order.paid, listing.submitted, listing.updated ...
  aggregate_type VARCHAR(40) NOT NULL,      -- order | listing
  aggregate_id BIGINT UNSIGNED NOT NULL,
  payload      JSON NOT NULL,
  status       ENUM('pending','delivered','failed','acknowledged') NOT NULL DEFAULT 'pending',
  attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  delivered_at DATETIME NULL,
  acknowledged_at DATETIME NULL,
  KEY idx_pms_status (status, created_at),
  KEY idx_pms_agg (aggregate_type, aggregate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_log (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     BIGINT UNSIGNED NULL,
  action      VARCHAR(60) NOT NULL,
  subject_type VARCHAR(40) NULL,
  subject_id  BIGINT UNSIGNED NULL,
  meta        JSON NULL,
  ip_address  VARCHAR(45) NULL,
  user_agent  VARCHAR(255) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_act_user (user_id, created_at),
  KEY idx_act_subject (subject_type, subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limiting for auth / chat / posting
CREATE TABLE rate_limits (
  bucket      VARCHAR(120) NOT NULL PRIMARY KEY,
  hits        INT UNSIGNED NOT NULL DEFAULT 0,
  window_ends DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
