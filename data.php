<?php
/* Copyright (c) 2021-2024 by Inoland */


/*

UPDATE data_lor
SET puid = NULL,
    datetime = NULL,
    correct = NULL;

*/


enum StatusLor: int
{
    case Unknown = 0;
    case Start = 1;
    case Lose = 2;
    case Win = 3;
    case UnknownLose = 4;
}