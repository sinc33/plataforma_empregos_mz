import React, { useState } from 'react';
import {
  Container, Paper, Box, Typography, Grid, TextField, Button,
  Chip, Avatar, Divider, IconButton, Stack
} from '@mui/material';
import { UploadFile, Save, Delete, Add } from '@mui/icons-material';
import { useForm, Controller, useFieldArray } from 'react-hook-form';
import { yupResolver } from '@hookform/resolvers/yup';
import * as yup from 'yup';
import { Helmet } from 'react-helmet-async';

const schema = yup.object({
  name: yup.string().required('Nome é obrigatório'),
  title: yup.string().required('Cargo atual é obrigatório'),
  summary: yup.string().required('Resumo é obrigatório'),
  phone: yup.string().required('Telefone é obrigatório'),
  location: yup.string().required('Localização é obrigatória'),
  skills: yup.array().of(yup.string()).min(1, 'Adicione pelo menos 1 competência')
});

function CandidateProfile() {
  const [cvFile, setCvFile] = useState(null);

  const { control, handleSubmit, formState: { errors }, reset } = useForm({
    resolver: yupResolver(schema),
    defaultValues: {
      name: '',
      title: '',
      summary: '',
      phone: '',
      location: '',
      skills: [],
      experience: [{ company: '', role: '', period: '', description: '' }],
      education: [{ institution: '', course: '', period: '' }]
    }
  });

  const { fields: expFields, append: appendExp, remove: removeExp } = useFieldArray({ control, name: 'experience' });
  const { fields: eduFields, append: appendEdu, remove: removeEdu } = useFieldArray({ control, name: 'education' });

  const onSubmit = async (data) => {
    // TODO: enviar para API
    console.log('Salvar perfil', data, cvFile);
  };

  return (
    <>
      <Helmet>
        <title>Perfil do Candidato</title>
      </Helmet>
      <Container maxWidth="md" sx={{ py: 4 }}>
        <Paper sx={{ p: 3 }}>
          <Typography variant="h5" fontWeight="bold" gutterBottom>
            Meu Perfil
          </Typography>
          <Divider sx={{ mb: 3 }} />

          <Box component="form" onSubmit={handleSubmit(onSubmit)}>
            <Grid container spacing={2}>
              <Grid item xs={12} md={3}>
                <Stack alignItems="center" spacing={2}>
                  <Avatar sx={{ width: 96, height: 96 }}>U</Avatar>
                  <Button variant="outlined" startIcon={<UploadFile />}>Foto</Button>
                </Stack>
              </Grid>
              <Grid item xs={12} md={9}>
                <Controller name="name" control={control} render={({ field }) => (
                  <TextField {...field} fullWidth label="Nome Completo" margin="normal" error={!!errors.name} helperText={errors.name?.message} />
                )} />
                <Controller name="title" control={control} render={({ field }) => (
                  <TextField {...field} fullWidth label="Cargo Atual" margin="normal" error={!!errors.title} helperText={errors.title?.message} />
                )} />
                <Controller name="summary" control={control} render={({ field }) => (
                  <TextField {...field} fullWidth multiline rows={3} label="Resumo Profissional" margin="normal" error={!!errors.summary} helperText={errors.summary?.message} />
                )} />
              </Grid>

              <Grid item xs={12} md={6}>
                <Controller name="phone" control={control} render={({ field }) => (
                  <TextField {...field} fullWidth label="Telefone" margin="normal" error={!!errors.phone} helperText={errors.phone?.message} />
                )} />
              </Grid>
              <Grid item xs={12} md={6}>
                <Controller name="location" control={control} render={({ field }) => (
                  <TextField {...field} fullWidth label="Localização" margin="normal" error={!!errors.location} helperText={errors.location?.message} />
                )} />
              </Grid>

              {/* Competências */}
              <Grid item xs={12}>
                <Typography variant="h6" gutterBottom>Competências</Typography>
                <Controller name="skills" control={control} render={({ field }) => (
                  <Box sx={{ display: 'flex', gap: 1, flexWrap: 'wrap' }}>
                    {field.value?.map((skill, idx) => (
                      <Chip key={idx} label={skill} onDelete={() => field.onChange(field.value.filter((_, i) => i !== idx))} />
                    ))}
                    <TextField size="small" placeholder="Adicionar competência" onKeyDown={(e) => {
                      if (e.key === 'Enter') {
                        e.preventDefault();
                        const v = e.currentTarget.value.trim();
                        if (v) field.onChange([...(field.value || []), v]);
                        e.currentTarget.value = '';
                      }
                    }} />
                  </Box>
                )} />
                {errors.skills && <Typography variant="caption" color="error">{errors.skills.message}</Typography>}
              </Grid>

              {/* Experiência */}
              <Grid item xs={12}>
                <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mt: 2 }}>
                  <Typography variant="h6">Experiência</Typography>
                  <Button startIcon={<Add />} onClick={() => appendExp({ company: '', role: '', period: '', description: '' })}>Adicionar</Button>
                </Box>
                {expFields.map((field, index) => (
                  <Paper key={field.id} sx={{ p: 2, mt: 1 }}>
                    <Grid container spacing={2}>
                      <Grid item xs={12} md={6}>
                        <Controller name={`experience.${index}.company`} control={control} render={({ field }) => (
                          <TextField {...field} fullWidth label="Empresa" />
                        )} />
                      </Grid>
                      <Grid item xs={12} md={6}>
                        <Controller name={`experience.${index}.role`} control={control} render={({ field }) => (
                          <TextField {...field} fullWidth label="Cargo" />
                        )} />
                      </Grid>
                      <Grid item xs={12} md={6}>
                        <Controller name={`experience.${index}.period`} control={control} render={({ field }) => (
                          <TextField {...field} fullWidth label="Período" />
                        )} />
                      </Grid>
                      <Grid item xs={12}>
                        <Controller name={`experience.${index}.description`} control={control} render={({ field }) => (
                          <TextField {...field} fullWidth label="Descrição" multiline rows={2} />
                        )} />
                      </Grid>
                      <Grid item xs={12}>
                        <Button color="error" startIcon={<Delete />} onClick={() => removeExp(index)}>Remover</Button>
                      </Grid>
                    </Grid>
                  </Paper>
                ))}
              </Grid>

              {/* Formação */}
              <Grid item xs={12}>
                <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mt: 2 }}>
                  <Typography variant="h6">Formação</Typography>
                  <Button startIcon={<Add />} onClick={() => appendEdu({ institution: '', course: '', period: '' })}>Adicionar</Button>
                </Box>
                {eduFields.map((field, index) => (
                  <Paper key={field.id} sx={{ p: 2, mt: 1 }}>
                    <Grid container spacing={2}>
                      <Grid item xs={12} md={6}>
                        <Controller name={`education.${index}.institution`} control={control} render={({ field }) => (
                          <TextField {...field} fullWidth label="Instituição" />
                        )} />
                      </Grid>
                      <Grid item xs={12} md={6}>
                        <Controller name={`education.${index}.course`} control={control} render={({ field }) => (
                          <TextField {...field} fullWidth label="Curso" />
                        )} />
                      </Grid>
                      <Grid item xs={12} md={6}>
                        <Controller name={`education.${index}.period`} control={control} render={({ field }) => (
                          <TextField {...field} fullWidth label="Período" />
                        )} />
                      </Grid>
                      <Grid item xs={12}>
                        <Button color="error" startIcon={<Delete />} onClick={() => removeEdu(index)}>Remover</Button>
                      </Grid>
                    </Grid>
                  </Paper>
                ))}
              </Grid>

              {/* Upload de CV */}
              <Grid item xs={12}>
                <Typography variant="h6" sx={{ mt: 2 }}>Currículo (PDF)</Typography>
                <Button component="label" variant="outlined" startIcon={<UploadFile />} sx={{ mt: 1 }}>
                  Carregar CV
                  <input type="file" hidden accept="application/pdf" onChange={(e) => setCvFile(e.target.files?.[0] || null)} />
                </Button>
                {cvFile && (
                  <Typography variant="body2" sx={{ mt: 1 }}>
                    Selecionado: {cvFile.name}
                  </Typography>
                )}
              </Grid>

              <Grid item xs={12}>
                <Box sx={{ display: 'flex', gap: 2, mt: 2 }}>
                  <Button type="submit" variant="contained" startIcon={<Save />}>Guardar</Button>
                  <Button variant="outlined" onClick={() => reset()}>Cancelar</Button>
                </Box>
              </Grid>
            </Grid>
          </Box>
        </Paper>
      </Container>
    </>
  );
}

export default CandidateProfile;
