<?php

namespace App\System\Configuration;

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
        'style'           => NavigationStyle::Default,
        'show_home'       => true,
        'home_icon'       => 'fa fa-home',
        'home_icon_only'  => false,
        'show_quicklinks' => true,
    ];


    public function __construct(
        protected readonly string $title = 'Unchained',
        protected array $dashboard = [],
        protected array $navigation = [],
    ) {
        $this->dashboard  = array_replace_recursive(self::DEFAULT_DASHBOARD, $this->dashboard);
        $this->navigation = array_replace_recursive(self::DEFAULT_NAVIGATION, $this->navigation);

        $this->validate();
    }

    private function validate(): void
    {
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

        $this->dashboard['style']          = $dashboardStyle->convert();
        $this->dashboard['show_app_stack'] = filter_var($this->dashboard['show_app_stack'], FILTER_VALIDATE_BOOL);
        $this->dashboard['show_count'] = filter_var($this->dashboard['show_count'], FILTER_VALIDATE_BOOL);

        $this->navigation['style']           = $navigationStyle->convert();
        $this->navigation['show_home']       = filter_var($this->navigation['show_home'], FILTER_VALIDATE_BOOL);
        $this->navigation['home_icon_only']  = filter_var($this->navigation['home_icon_only'], FILTER_VALIDATE_BOOL);
        $this->navigation['show_quicklinks'] = filter_var($this->navigation['show_quicklinks'], FILTER_VALIDATE_BOOL);
    }

    public function getDashboard(string $elem, mixed $default = null): mixed
    {
        return $this->dashboard[$elem] ?? $default;
    }

    public function getNavigation(string $elem, mixed $default = null): mixed
    {
        return $this->navigation[$elem] ?? $default;
    }

    public function toArray(): array
    {
        return [
            'dashboard'  => $this->dashboard,
            'navigation' => $this->navigation,
            'title'      => $this->title,
        ];
    }
}