<?php

namespace App\Filament\Admin\Pages;

use Awcodes\Overlook\Widgets\OverlookWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\WidgetConfiguration;

class Dashboard extends BaseDashboard
{
    /**
     * Overlook menampilkan ringkasan resource master data dan hanya relevan
     * untuk admin. Widget lain tetap ditampilkan untuk semua role.
     *
     * @return array<class-string | WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        $widgets = parent::getWidgets();

        if (auth()->user()?->isAdmin()) {
            return $widgets;
        }

        return array_values(array_filter(
            $widgets,
            static function (string|WidgetConfiguration $widget): bool {
                $class = $widget instanceof WidgetConfiguration
                    ? $widget->widget
                    : $widget;

                return $class !== OverlookWidget::class;
            }
        ));
    }
}
