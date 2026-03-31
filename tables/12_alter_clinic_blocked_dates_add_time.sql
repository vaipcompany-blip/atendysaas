-- Migration 12: adicionar faixa de hor�rio em bloqueios da cl�nica
-- Permite bloquear apenas parte do dia (ex: 14:00-15:30)
ALTER TABLE clinic_blocked_dates
    ADD COLUMN IF NOT EXISTS start_time TIME NULL AFTER blocked_date,
    ADD COLUMN IF NOT EXISTS end_time TIME NULL AFTER start_time;


