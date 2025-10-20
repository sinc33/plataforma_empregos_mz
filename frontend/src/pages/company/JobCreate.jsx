import React, { useState } from 'react';
import { Container, Paper, Box, Typography, TextField, Button, Grid, Chip, Switch, FormControlLabel } from '@mui/material';
import { Helmet } from 'react-helmet-async';
import { useForm, Controller } from 'react-hook-form';
import { yupResolver } from '@hookform/resolvers/yup';
import * as yup from 'yup';

const schema = yup.object({
  title: yup.string().required('Título é obrigatório'),
  location: yup.string().required('Localização é obrigatória'),
  salary: yup.string().required('Faixa salarial é obrigatória'),
  description: yup.string().required('Descrição é obrigatória'),
});

function CompanyJobCreate() {
  const { control, handleSubmit, reset, setValue, watch } = useForm({
    resolver: yupResolver(schema),
    defaultValues: {
      title: '', location: '', salary: '', description: '', skills: [], remote: false
    }
  });
  const skills = watch('skills');

  const onSubmit = async (data) => {
    // TODO: enviar para API
    console.log('Criar vaga', data);
  };

  return (
    <>
      <Helmet>
        <title>Criar Nova Vaga</title>
      </Helmet>
      <Container maxWidth="md" sx={{ py: 4 }}>
        <Paper sx={{ p: 3 }}>
          <Typography variant="h5" fontWeight="bold" gutterBottom>
            Criar Nova Vaga
          </Typography>

          <Box component="form" onSubmit={handleSubmit(onSubmit)}>
            <Controller name="title" control={control} render={({ field, fieldState }) => (
              <TextField {...field} fullWidth label="Título da Vaga" margin="normal" error={!!fieldState.error} helperText={fieldState.error?.message} />
            )} />
            <Controller name="location" control={control} render={({ field, fieldState }) => (
              <TextField {...field} fullWidth label="Localização" margin="normal" error={!!fieldState.error} helperText={fieldState.error?.message} />
            )} />
            <Controller name="salary" control={control} render={({ field, fieldState }) => (
              <TextField {...field} fullWidth label="Faixa Salarial" margin="normal" error={!!fieldState.error} helperText={fieldState.error?.message} />
            )} />
            <Controller name="description" control={control} render={({ field, fieldState }) => (
              <TextField {...field} fullWidth multiline rows={4} label="Descrição da Vaga" margin="normal" error={!!fieldState.error} helperText={fieldState.error?.message} />
            )} />

            <Box sx={{ mt: 2 }}>
              <Typography variant="subtitle1" gutterBottom>Competências</Typography>
              <Box sx={{ display: 'flex', gap: 1, flexWrap: 'wrap' }}>
                {skills.map((s, i) => (
                  <Chip key={i} label={s} onDelete={() => setValue('skills', skills.filter((_, idx) => idx !== i))} />
                ))}
                <TextField size="small" placeholder="Adicionar competência" onKeyDown={(e) => {
                  if (e.key === 'Enter') {
                    e.preventDefault();
                    const v = e.currentTarget.value.trim();
                    if (v) setValue('skills', [...skills, v]);
                    e.currentTarget.value = '';
                  }
                }} />
              </Box>
            </Box>

            <FormControlLabel control={<Controller name="remote" control={control} render={({ field }) => (
              <Switch {...field} checked={field.value} />
            )} />} label="Vaga Remota" sx={{ mt: 2 }} />

            <Box sx={{ display: 'flex', gap: 2, mt: 3 }}>
              <Button type="submit" variant="contained">Publicar</Button>
              <Button variant="outlined" onClick={() => reset()}>Cancelar</Button>
            </Box>
          </Box>
        </Paper>
      </Container>
    </>
  );
}

export default CompanyJobCreate;
