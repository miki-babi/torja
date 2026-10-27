<?php

namespace App\Enums;

enum MealType: string
{
    case Breakfast = 'breakfast';
    case Snack = 'snack';
    case Lunch = 'lunch';
    case PostWorkout = 'post_workout';
    case Dinner = 'dinner';
    case BeforeBed = 'before_bed';
}
