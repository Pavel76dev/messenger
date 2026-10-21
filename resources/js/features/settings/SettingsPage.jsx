import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link as RouterLink } from 'react-router-dom';
import {
  Alert,
  Box,
  Button,
  Checkbox,
  Container,
  FormControlLabel,
  FormGroup,
  FormHelperText,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material';
import { fetchUserSettings, updateUserSettings } from './settingsApi';

function groupDefinitions(definitions) {
  const groups = new Map();
  for (const def of definitions) {
    const key = def.group || 'general';
    if (!groups.has(key)) {
      groups.set(key, {
        id: key,
        label: def.group_label || key,
        items: [],
      });
    }
    groups.get(key).items.push(def);
  }
  return Array.from(groups.values());
}

function isChecked(value) {
  return value === '1' || value === 1 || value === true || value === 'true';
}

export function SettingsPage() {
  const [definitions, setDefinitions] = useState([]);
  const [values, setValues] = useState({});
  const [defaults, setDefaults] = useState({});
  const [overridden, setOverridden] = useState({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  const applyPayload = (data, fallbackDefs = []) => {
    const defs = Array.isArray(data.definitions) ? data.definitions : fallbackDefs;
    setDefinitions(defs);
    setValues(data.values && typeof data.values === 'object' ? { ...data.values } : {});
    setOverridden(
      data.overridden && typeof data.overridden === 'object' ? { ...data.overridden } : {},
    );
    const defMap = {};
    defs.forEach((d) => {
      defMap[d.key] = d.default ?? (d.type === 'checkbox' ? '0' : '');
    });
    setDefaults(defMap);
  };

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const data = await fetchUserSettings();
      applyPayload(data);
    } catch (err) {
      setError(err?.message || 'Не удалось загрузить настройки.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const groups = useMemo(() => groupDefinitions(definitions), [definitions]);

  const handleChange = (key, next) => {
    setValues((prev) => ({ ...prev, [key]: next }));
    setSuccess('');
  };

  const handleResetGroup = (groupItems) => {
    setValues((prev) => {
      const next = { ...prev };
      groupItems.forEach((def) => {
        next[def.key] = defaults[def.key] ?? (def.type === 'checkbox' ? '0' : '');
      });
      return next;
    });
    setSuccess('');
  };

  const handleSave = async (event) => {
    event.preventDefault();
    setSaving(true);
    setError('');
    setSuccess('');
    try {
      const payload = {};
      for (const def of definitions) {
        const current = String(values[def.key] ?? (def.type === 'checkbox' ? '0' : ''));
        const defVal = String(defaults[def.key] ?? (def.type === 'checkbox' ? '0' : ''));
        if (current === defVal && !overridden[def.key]) {
          continue;
        }
        if (current === defVal && overridden[def.key]) {
          payload[def.key] = null;
        } else {
          payload[def.key] = current;
        }
      }
      const data = await updateUserSettings(payload);
      applyPayload(data, definitions);
      setSuccess('Настройки сохранены');
    } catch (err) {
      setError(err?.message || 'Не удалось сохранить.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Container maxWidth="md" sx={{ py: 4 }}>
      <Stack direction="row" spacing={1} sx={{ mb: 2 }}>
        <Button component={RouterLink} to="/">
          ← К чатам
        </Button>
        <Button component={RouterLink} to="/profile" color="inherit">
          Профиль
        </Button>
      </Stack>

      <Paper sx={{ p: 3 }}>
        <Typography variant="h5" gutterBottom>
          Настройки
        </Typography>
        <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
          Здесь можно обычным языком сказать ИИ, как составлять список дел из голосовых
          записей. Технические промпты подставляются автоматически.
        </Typography>

        {error ? (
          <Alert severity="error" sx={{ mb: 2 }}>
            {error}
          </Alert>
        ) : null}
        {success ? (
          <Alert severity="success" sx={{ mb: 2 }}>
            {success}
          </Alert>
        ) : null}

        {loading ? (
          <Typography variant="body2" color="text.secondary">
            Загрузка…
          </Typography>
        ) : (
          <Box component="form" onSubmit={handleSave}>
            <Stack spacing={3}>
              {groups.map((group) => {
                const textItems = group.items.filter((d) => d.type !== 'checkbox');
                const checkItems = group.items.filter((d) => d.type === 'checkbox');
                const groupDirty = group.items.some(
                  (def) =>
                    String(values[def.key] ?? '') !== String(defaults[def.key] ?? '') ||
                    overridden[def.key],
                );

                return (
                  <Box key={group.id}>
                    <Stack
                      direction="row"
                      alignItems="center"
                      justifyContent="space-between"
                      sx={{ mb: 1 }}
                    >
                      <Typography variant="h6">{group.label}</Typography>
                      <Button
                        size="small"
                        disabled={saving || !groupDirty}
                        onClick={() => handleResetGroup(group.items)}
                      >
                        Сбросить группу
                      </Button>
                    </Stack>

                    <Stack spacing={2}>
                      {textItems.map((def) => (
                        <TextField
                          key={def.key}
                          label={def.label}
                          value={values[def.key] ?? ''}
                          onChange={(e) => handleChange(def.key, e.target.value)}
                          fullWidth
                          multiline={def.type === 'textarea'}
                          minRows={def.type === 'textarea' ? 4 : 1}
                          placeholder={def.placeholder || undefined}
                          helperText={def.description || undefined}
                          inputProps={{ maxLength: def.max || 4000 }}
                        />
                      ))}

                      {checkItems.length > 0 ? (
                        <Box>
                          <Typography variant="subtitle2" sx={{ mb: 0.5 }}>
                            Как составлять список
                          </Typography>
                          <FormGroup>
                            {checkItems.map((def) => (
                              <Box key={def.key} sx={{ mb: 0.5 }}>
                                <FormControlLabel
                                  control={
                                    <Checkbox
                                      checked={isChecked(values[def.key])}
                                      onChange={(e) =>
                                        handleChange(def.key, e.target.checked ? '1' : '0')
                                      }
                                    />
                                  }
                                  label={def.label}
                                />
                                {def.description ? (
                                  <FormHelperText sx={{ ml: 4, mt: -0.5 }}>
                                    {def.description}
                                  </FormHelperText>
                                ) : null}
                              </Box>
                            ))}
                          </FormGroup>
                        </Box>
                      ) : null}
                    </Stack>
                  </Box>
                );
              })}
              <Button type="submit" variant="contained" disabled={saving || definitions.length === 0}>
                {saving ? 'Сохранение…' : 'Сохранить'}
              </Button>
            </Stack>
          </Box>
        )}
      </Paper>
    </Container>
  );
}
