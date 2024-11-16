<?php

class RoomEntity
{
    public $id;
    public $room_name;
    public $room_code;
    public $max_attempts;

    public function __construct($room_name, $room_code, $max_attempts)
    {
        $this->room_name = $room_name;
        $this->room_code = $room_code;
        $this->max_attempts = $max_attempts;
    }
}
