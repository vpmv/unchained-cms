<?php

namespace App\System\Configuration;

enum Themes: string
{
    case Dark = 'dark';
    case Light = 'light';
    case Auto = 'auto';
}

enum DashboardStyle: string
{
    case Default = 'default';
    case Text = 'text';
    case Block = 'block';

    public function convert(): string
    {
        return match ($this) {
            DashboardStyle::Default, DashboardStyle::Text => DashboardStyle::Default->value,
            DashboardStyle::Block => DashboardStyle::Block->value,
        };
    }
}

enum NavigationStyle: string
{
    case Default = 'default';
    case Top = 'top';
    case Side = 'side';
    case Offcanvas = 'offcanvas';

    public function convert(): string
    {
        return match ($this) {
            NavigationStyle::Default, NavigationStyle::Top => NavigationStyle::Default->value,
            NavigationStyle::Side, NavigationStyle::Offcanvas => NavigationStyle::Side->value,
        };
    }
}

class UnchainedConfig
{
    protected const DEFAULT_DASHBOARD  = [
        'style'          => 'default',
        'show_app_stack' => true,
        'show_count'     => true,
    ];
    protected const DEFAULT_NAVIGATION = [
        'style'               => NavigationStyle::Default,
        'show_home'           => true,
        'home_icon'           => 'fa fa-home',
        'home_icon_only'      => false,
        'show_quicklinks'     => true,
        'show_theme_switcher' => true,
    ];


    public function __construct(
        protected readonly string $title = 'Unchained',
        private string $title_icon = 'fa fa-link',
        protected string $theme = 'auto',
        protected array $dashboard = [],
        protected array $navigation = [],
        protected array $locales = [],
        protected ?string $locale = null,
    ) {
        $this->dashboard  = array_replace_recursive(self::DEFAULT_DASHBOARD, $this->dashboard);
        $this->navigation = array_replace_recursive(self::DEFAULT_NAVIGATION, $this->navigation);

        if ($this->locales) {
            if (!$this->locale || !in_array($this->locale, $this->locales)) {
                $this->locale = $this->locales[0];
            }
        }

        $this->validate();
    }

    /**
     * Validate input
     *
     * @return void
     */
    private function validate(): void
    {
        try {
            $this->theme = Themes::from($this->theme)->value;
        } catch (\ValueError) {
            $this->theme = Themes::Auto->value;
        }

        try {
            $dashboardStyle = DashboardStyle::from($this->dashboard['style']);
        } catch (\ValueError) {
            $dashboardStyle = DashboardStyle::Default;
        }

        try {
            $navigationStyle = NavigationStyle::from($this->navigation['style']);
        } catch (\ValueError) {
            $navigationStyle = NavigationStyle::Default;
        }


        $this->dashboard['style'] = $dashboardStyle->convert();
        foreach ($this->dashboard as $key => &$value) {
            if (str_starts_with($key, 'show_')) {
                $value = filter_var($value, FILTER_VALIDATE_BOOL);
            }
        }
        $this->navigation['style'] = $navigationStyle->convert();
        foreach ($this->navigation as $key => &$value) {
            if (str_starts_with($key, 'show_')) {
                $value = filter_var($value, FILTER_VALIDATE_BOOL);
            }
        }
    }

    /**
     * Get dashboard config
     *
     * @param string     $elem
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function getDashboard(string $elem, mixed $default = null): mixed
    {
        return $this->dashboard[$elem] ?? $default;
    }

    /**
     * Get navigation config
     *
     * @param string     $elem
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function getNavigation(string $elem, mixed $default = null): mixed
    {
        return $this->navigation[$elem] ?? $default;
    }

    /**
     * Get default locale
     *
     * @return string|null
     */
    public function getLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * Get configured locales
     *
     * @return array
     */
    public function getLocales(): array
    {
        return $this->locales;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'title'      => $this->title,
            'title_icon' => $this->title_icon,
            'theme'      => $this->theme,
            'locales'    => $this->locales,
            'dashboard'  => $this->dashboard,
            'navigation' => $this->navigation,
        ];
    }
}